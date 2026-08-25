"""Tool registry: allowlisted tools with Pydantic-validated arguments.

Registry is a plain dictionary (no eval / no dynamic imports / no code
execution). The model may only propose calls; the server validates arguments,
injects the tenant from auth context, applies policy, and sanitizes output.
"""

from __future__ import annotations

import time
from collections.abc import Awaitable, Callable
from dataclasses import dataclass
from typing import Any

from pydantic import BaseModel, ValidationError

from sipromo.application.policies.tool_policy import ToolPolicy
from sipromo.application.ports.llm import ToolDeclaration
from sipromo.application.ports.tool_executor import (
    ExecutionContext,
    ToolExecutorPort,
    ToolRegistryPort,
    ToolResult,
    ValidatedToolCall,
)
from sipromo.domain.exceptions import ToolBudgetExceeded  # noqa: F401 (re-export for tests)

# Field names stripped recursively from any tool payload sent to the model.
PRIVATE_FIELD_NAMES = frozenset(
    {
        "password",
        "token",
        "access_token",
        "refresh_token",
        "api_key",
        "api_secret",
        "secret",
        "phone",
        "phone_number",
        "email",
        "ssn",
        "nik",
        "bank_account",
        "bank_number",
        "invite_token",
        "reset_token",
        "session",
    }
)


ToolHandler = Callable[[dict[str, Any], ExecutionContext], Awaitable[dict[str, Any] | list[Any]]]


def _dereference(schema: dict[str, Any], defs: dict[str, Any]) -> dict[str, Any]:
    """Resolve JSON Schema $ref pointers so provider APIs accept the schema."""
    if "$ref" in schema:
        name = schema["$ref"].rsplit("/", 1)[-1]
        target = defs.get(name, {})
        if "$ref" in target:
            return _dereference(target, defs)
        return _dereference({k: v for k, v in target.items() if k != "$defs"}, defs)
    resolved: dict[str, Any] = {}
    for key, value in schema.items():
        if key == "$defs":
            continue
        if isinstance(value, dict):
            resolved[key] = _dereference(value, defs)
        elif isinstance(value, list):
            resolved[key] = [
                _dereference(item, defs) if isinstance(item, dict) else item for item in value
            ]
        else:
            resolved[key] = value
    return resolved


@dataclass
class Tool:
    name: str
    description: str
    args_model: type[BaseModel]
    handler: ToolHandler
    is_write: bool = False
    max_args: int | None = None

    def json_schema(self) -> dict[str, Any]:
        schema = self.args_model.model_json_schema()
        return _dereference(
            {
                "type": "object",
                "properties": schema.get("properties", {}),
                "required": schema.get("required", []),
            },
            schema.get("$defs", {}),
        )


class ToolRegistry(ToolRegistryPort):
    def __init__(self) -> None:
        self._tools: dict[str, Tool] = {}

    def register(self, tool: Tool) -> None:
        if tool.name in self._tools:
            raise ValueError(f"tool '{tool.name}' already registered")
        self._tools[tool.name] = tool

    def find(self, tool_name: str) -> Tool | None:
        return self._tools.get(tool_name)

    def validate(self, tool_call: Any) -> ValidatedToolCall:
        tool = self.find(tool_call.tool_name)
        if tool is None:
            raise KeyError(f"unknown tool '{tool_call.tool_name}'")
        arguments = tool_call.arguments or {}
        if tool.max_args is not None and len(arguments) > tool.max_args:
            raise ValidationError.from_exception_data(
                tool.name,
                [{"type": "too_many_args", "loc": ("arguments",), "input": arguments}],
            )
        validated = tool.args_model.model_validate(arguments)
        return ValidatedToolCall(
            call_id=tool_call.call_id,
            tool_name=tool_call.tool_name,
            arguments=validated.model_dump(mode="json"),
            round_index=getattr(tool_call, "round_index", 0),
        )

    def allowed_tools(self, role: str) -> list[str]:
        return ToolPolicy.authorized_tool_names(role)

    def declarations(self) -> list[ToolDeclaration]:
        return [
            ToolDeclaration(
                name=tool.name,
                description=tool.description,
                parameters=tool.json_schema(),
            )
            for tool in self._tools.values()
            if not tool.is_write
        ]


def sanitize_payload(payload: Any) -> Any:
    """Recursively strip private fields before the payload reaches the model."""
    if isinstance(payload, dict):
        return {
            k: sanitize_payload(v)
            for k, v in payload.items()
            if k.lower() not in PRIVATE_FIELD_NAMES
        }
    if isinstance(payload, list):
        return [sanitize_payload(item) for item in payload]
    return payload


class ToolExecutor(ToolExecutorPort):
    """Executes validated tool calls with timeout, audit, and sanitization."""

    def __init__(self, registry: ToolRegistry) -> None:
        self._registry = registry

    async def execute(self, tool_call: ValidatedToolCall, context: ExecutionContext) -> ToolResult:
        started = time.monotonic()
        tool = self._registry.find(tool_call.tool_name)
        if tool is None:
            return ToolResult(
                call_id=tool_call.call_id,
                tool_name=tool_call.tool_name,
                status="denied",
                error_code="TOOL_NOT_FOUND",
                error_message="unknown tool",
                latency_ms=int((time.monotonic() - started) * 1000),
            )
        if not ToolPolicy.allowed_for_role(tool_call.tool_name, context.role):
            return ToolResult(
                call_id=tool_call.call_id,
                tool_name=tool_call.tool_name,
                status="denied",
                error_code="TOOL_DENIED",
                error_message="tool not allowed for role",
                latency_ms=int((time.monotonic() - started) * 1000),
            )
        try:
            import asyncio

            payload = await asyncio.wait_for(
                tool.handler(tool_call.arguments, context),
                timeout=context.timeout_seconds,
            )
            return ToolResult(
                call_id=tool_call.call_id,
                tool_name=tool_call.tool_name,
                status="succeeded",
                data=sanitize_payload(payload),
                duration_ms=int((time.monotonic() - started) * 1000),
                latency_ms=int((time.monotonic() - started) * 1000),
            )
        except TimeoutError:
            return ToolResult(
                call_id=tool_call.call_id,
                tool_name=tool_call.tool_name,
                status="failed",
                error_code="TOOL_TIMEOUT",
                error_message="tool execution timed out",
                duration_ms=int((time.monotonic() - started) * 1000),
                latency_ms=int((time.monotonic() - started) * 1000),
            )
        except Exception as exc:
            return ToolResult(
                call_id=tool_call.call_id,
                tool_name=tool_call.tool_name,
                status="failed",
                error_code="TOOL_ERROR",
                error_message=f"{type(exc).__name__}: {str(exc)[:200]}",
                duration_ms=int((time.monotonic() - started) * 1000),
                latency_ms=int((time.monotonic() - started) * 1000),
            )
