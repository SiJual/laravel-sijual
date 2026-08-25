"""OpenAI-compatible adapter implementing LLMPort via chat completions
(httpx). Serves OpenAI chat completions and any OpenAI-compatible gateway.
configuration; embeddings and poster image generation stay provider-agnostic.

Protocol mapping (OpenAI shape):
- tools -> tools[{type: "function", function: {name, description, parameters}}]
- json_schema -> response_format {type: "json_schema", json_schema: {...}}
- tool results are injected as user context blocks (same markers as the
  Gemini adapter) - keeps the adapter stateless across multi-turn rounds.
"""

from __future__ import annotations

import hashlib
import json
import logging
import re
from typing import Any

import httpx
from tenacity import (
    retry,
    retry_if_exception,
    stop_after_attempt,
    wait_exponential_jitter,
)

from sipromo.application.ports.llm import (
    LLMPort,
    LLMRequest,
    LLMTurn,
    ToolCallProposal,
    ToolDeclaration,
    ToolResultForModel,
)
from sipromo.domain.exceptions import (
    ApplicationError,
    ProviderQuotaExhausted,
    ProviderTimeoutError,
)

logger = logging.getLogger(__name__)

_BASE_URL = "https://api.openai.com/v1"

_JSON_BLOCK_RE = re.compile(r"```(?:json)?\s*(.*?)```", re.DOTALL)


def _strip_json_fences(text: str) -> str:
    match = _JSON_BLOCK_RE.search(text)
    if match:
        return match.group(1).strip()
    return text.strip()


# OpenAI's JSON schema mode rejects length/numeric bounds; keep only the shape.
_UNSUPPORTED_SCHEMA_KEYS = frozenset(
    {
        "default",
        "examples",
        "minLength",
        "maxLength",
        "minItems",
        "maxItems",
        "minimum",
        "maximum",
        "exclusiveMinimum",
        "exclusiveMaximum",
        "format",
    }
)


def _simplify_schema(node: dict[str, Any] | list[Any] | str | int | float | bool | None) -> Any:
    if isinstance(node, dict):
        return {
            key: _simplify_schema(value)
            for key, value in node.items()
            if key not in _UNSUPPORTED_SCHEMA_KEYS and value is not None
        }
    if isinstance(node, list):
        return [_simplify_schema(item) for item in node]
    return node


_RETRYABLE_CODES = {"500", "502", "503", "504"}
_QUOTA_CODES = {"429"}


def _is_retryable(exc: BaseException) -> bool:
    if isinstance(exc, ProviderTimeoutError):
        return True
    if isinstance(exc, ApplicationError) and exc.retryable:
        return True
    code = getattr(exc, "code", "") or ""
    return str(code) in _RETRYABLE_CODES


class OpenAICompatibleAdapter(LLMPort):
    """LLMPort over the OpenRouter chat completions endpoint."""

    def __init__(
        self,
        *,
        api_key: str,
        model: str,
        temperature: float,
        max_output_tokens: int,
        timeout_ms: int = 30_000,
        max_attempts: int = 5,
        base_url: str = _BASE_URL,
        provider_name: str = "openai-compatible",
        extra_headers: dict[str, str] | None = None,
        reasoning_effort: str | None = None,
    ) -> None:
        self._model = model
        self._provider_name = provider_name
        self._reasoning_effort = reasoning_effort
        self._temperature = temperature
        self._max_output_tokens = max_output_tokens
        self._timeout_ms = timeout_ms
        self._max_attempts = max_attempts
        self._base_url = base_url.rstrip("/")
        headers = {"Authorization": f"Bearer {api_key}"}
        if extra_headers:
            headers.update(extra_headers)
        self._client = httpx.AsyncClient(
            timeout=timeout_ms / 1000.0,
            headers=headers,
        )

    @property
    def provider_name(self) -> str:
        return self._provider_name

    @property
    def model_name(self) -> str:
        return self._model

    # ------------------------------------------------------------------ #

    async def generate_with_tools(self, request: LLMRequest) -> LLMTurn:
        messages = _build_messages(request)
        response = await self._call(messages, request)
        return _parse_turn(response, request)

    async def continue_with_tool_results(
        self,
        request: LLMRequest,
        tool_results: list[ToolResultForModel],
    ) -> LLMTurn:
        messages = _build_messages(request)
        for result in tool_results:
            messages.append({"role": "user", "content": _tool_result_text(result)})
        response = await self._call(messages, request)
        return _parse_turn(response, request)

    async def repair_output(
        self, request: LLMRequest, invalid_raw: dict, errors: list[str]
    ) -> dict | None:
        messages = _build_messages(request)
        repair_prompt = (
            "Output JSON tidak valid menurut schema. Perbaiki dan kembalikan hanya JSON valid.\n"
            f"Errors: {errors}\nInvalid output: "
            f"{json.dumps(invalid_raw, ensure_ascii=False)[:2000]}"
        )
        messages.append({"role": "user", "content": repair_prompt})
        response = await self._call(messages, request, repair_mode=True)
        text = _extract_text(response)
        if text is None:
            return None
        try:
            return json.loads(_strip_json_fences(text))
        except (json.JSONDecodeError, TypeError):
            logger.warning("repair output invalid json", extra={"hash": _hash(text[:2000])})
            return None

    # ------------------------------------------------------------------ #

    @retry(
        stop=stop_after_attempt(5),
        wait=wait_exponential_jitter(initial=5, max=60, jitter=5),
        retry=retry_if_exception(_is_retryable),
        reraise=True,
    )
    async def _call(
        self,
        messages: list[dict[str, Any]],
        request: LLMRequest,
        repair_mode: bool = False,
    ) -> dict[str, Any]:
        payload: dict[str, Any] = {
            "model": self._model,
            "messages": messages,
            "temperature": (
                request.temperature if request.temperature is not None else self._temperature
            ),
            "max_tokens": (
                request.max_output_tokens
                if request.max_output_tokens is not None
                else self._max_output_tokens
            ),
        }
        if self._reasoning_effort and self._provider_name == "openai":
            payload["reasoning_effort"] = self._reasoning_effort
        if request.tools and not (request.json_schema or repair_mode):
            payload["tools"] = [_to_openai_tool(t) for t in request.tools]
        if request.json_schema:
            payload["response_format"] = {
                "type": "json_schema",
                "json_schema": {
                    "name": "promotion_output",
                    "schema": _openai_schema(_simplify_schema(request.json_schema)),
                    "strict": True,
                },
            }
        repairs = 0
        while True:
            try:
                return await self._post(payload)
            except httpx.HTTPStatusError as exc:
                if exc.response.status_code not in {400, 422} or repairs >= 3:
                    raise _map_provider_error(exc) from exc
                message = _error_message(exc)
                if "max_completion_tokens" in message:
                    # GPT-5 family rejects max_tokens; use the newer name.
                    payload["max_completion_tokens"] = payload.pop("max_tokens")
                elif "temperature" in message:
                    # Reasoning models only accept the default temperature.
                    payload.pop("temperature", None)
                elif "reasoning_effort" in message:
                    payload.pop("reasoning_effort", None)
                elif request.json_schema and "response_format" in payload:
                    # Provider may not support json_schema response_format;
                    # retry with plain JSON instructions.
                    payload.pop("response_format", None)
                else:
                    raise _map_provider_error(exc) from exc
                repairs += 1
        raise ApplicationError(
            "provider request rejected (400)", error_code="PROVIDER_REQUEST_ERROR"
        )

    async def _post(self, payload: dict[str, Any]) -> dict[str, Any]:
        resp = await self._client.post(
            f"{self._base_url}/chat/completions",
            json=payload,
        )
        resp.raise_for_status()
        return resp.json()


# ---------------------------------------------------------------------- #
# helpers


def _to_openai_tool(tool: ToolDeclaration) -> dict[str, Any]:
    return {
        "type": "function",
        "function": {
            "name": tool.name,
            "description": tool.description,
            "parameters": tool.parameters,
        },
    }


def _build_messages(request: LLMRequest) -> list[dict[str, Any]]:
    messages: list[dict[str, Any]] = []
    if request.system_instruction:
        messages.append({"role": "system", "content": request.system_instruction})
    messages.append({"role": "user", "content": request.user_brief})
    for block in request.context_blocks:
        if block.kind == "rag_chunk":
            text = (
                f"<RETRIEVED_CONTEXT chunk_id={block.block_id}>\n"
                f"{block.content}\n</RETRIEVED_CONTEXT>"
            )
        else:
            text = f"<TOOL_RESULTS tool_call_id={block.block_id}>\n{block.content}\n</TOOL_RESULTS>"
        messages.append({"role": "user", "content": text})
    return messages


def _tool_result_text(result: ToolResultForModel) -> str:
    return (
        f"<TOOL_RESULTS tool_call_id={result.call_id}>\n"
        f"{json.dumps(result.data, ensure_ascii=False, default=str)}\n"
        f"</TOOL_RESULTS>"
    )


def _parse_turn(response: dict[str, Any], request: LLMRequest) -> LLMTurn:
    usage: dict[str, Any] = {}
    raw_usage = response.get("usage") or {}
    if raw_usage:
        usage = {
            "prompt_tokens": raw_usage.get("prompt_tokens", 0),
            "completion_tokens": raw_usage.get("completion_tokens", 0),
            "total_tokens": raw_usage.get("total_tokens", 0),
        }

    choices = response.get("choices") or []
    if not choices:
        return LLMTurn(usage=usage, model_name="", prompt_version=request.prompt_version)

    message = choices[0].get("message") or {}
    proposed = message.get("tool_calls") or []
    if proposed:
        tool_calls = [
            ToolCallProposal(
                call_id=tc.get("id")
                or _hash(
                    f"{fn.get('name')}:"
                    f"{json.dumps(_parse_arguments(fn.get('arguments')), sort_keys=True)}"
                ),
                tool_name=fn.get("name", ""),
                arguments=_parse_arguments(fn.get("arguments")),
            )
            for tc in proposed
            if (fn := tc.get("function") or {})
        ]
        return LLMTurn(
            tool_calls=tool_calls,
            usage=usage,
            model_name="",
            prompt_version=request.prompt_version,
        )

    text = message.get("content")
    if not text:
        return LLMTurn(usage=usage, model_name="", prompt_version=request.prompt_version)
    try:
        final_output = json.loads(_strip_json_fences(text))
        if isinstance(final_output, dict):
            return LLMTurn(
                final_output=final_output,
                usage=usage,
                model_name="",
                prompt_version=request.prompt_version,
                raw_response_hash=_hash(text),
            )
    except (json.JSONDecodeError, TypeError):
        logger.warning(
            "structured output invalid json",
            extra={"hash": _hash(text[:2000])},
        )
        return LLMTurn(
            final_output={"_invalid_json": text[:2000]},
            usage=usage,
            model_name="",
            prompt_version=request.prompt_version,
        )
    return LLMTurn(usage=usage, model_name="", prompt_version=request.prompt_version)


def _parse_arguments(raw: Any) -> dict[str, Any]:
    if isinstance(raw, dict):
        return dict(raw)
    if isinstance(raw, str):
        try:
            parsed = json.loads(raw)
            return dict(parsed) if isinstance(parsed, dict) else {}
        except (json.JSONDecodeError, TypeError):
            return {}
    return {}


def _extract_text(response: dict[str, Any]) -> str | None:
    choices = response.get("choices") or []
    if not choices:
        return None
    content = (choices[0].get("message") or {}).get("content")
    return content or None


def _error_message(exc: httpx.HTTPStatusError) -> str:
    try:
        body = exc.response.json()
        error = body.get("error") or {}
        return str(error.get("message", "") or exc)
    except (ValueError, AttributeError):
        return str(exc)


def _map_provider_error(exc: Exception) -> ApplicationError:
    if isinstance(exc, httpx.TimeoutException):
        return ProviderTimeoutError()
    if isinstance(exc, httpx.HTTPStatusError):
        code = str(exc.response.status_code)
        try:
            body = exc.response.json()
            error = body.get("error") or {}
            message = error.get("message", "") or str(exc)
        except (ValueError, AttributeError):
            message = str(exc)
        lowered = message.lower()
        if code in _QUOTA_CODES:
            if "rate" in lowered and "quota" not in lowered and "daily" not in lowered:
                # Upstream/rate limits recover with backoff (e.g. OpenRouter
                # ":free" shared pools return 429 "rate-limited upstream").
                return ApplicationError(
                    f"provider rate limited ({code}): {message}",
                    error_code="PROVIDER_UNAVAILABLE",
                    retryable=True,
                )
            return ProviderQuotaExhausted()
        if code in _RETRYABLE_CODES:
            return ApplicationError(
                f"provider temporary failure ({code})",
                error_code="PROVIDER_UNAVAILABLE",
                retryable=True,
            )
        return ApplicationError(
            f"provider request rejected ({code}): {message}",
            error_code="PROVIDER_REQUEST_ERROR",
        )
    message = str(exc)
    if isinstance(exc, TimeoutError) or "timeout" in message.lower():
        return ProviderTimeoutError()
    return ApplicationError(
        f"provider request rejected ({type(exc).__name__})",
        error_code="PROVIDER_REQUEST_ERROR",
    )


def _hash(text: str) -> str:
    return hashlib.sha256(text.encode("utf-8")).hexdigest()[:16]


def _openai_schema(node: Any) -> Any:
    """OpenAI strict mode requires additionalProperties=false and a
    required array covering every property; apply recursively."""
    if isinstance(node, dict):
        if node.get("type") == "object":
            node["additionalProperties"] = False
            props = node.get("properties")
            if isinstance(props, dict):
                node["required"] = sorted(props.keys())
        for key, value in node.items():
            if isinstance(value, (dict, list)):
                node[key] = _openai_schema(value)
    elif isinstance(node, list):
        return [_openai_schema(item) for item in node]
    return node
