"""LLM port. The application depends only on this protocol - never on provider SDKs."""

from __future__ import annotations

from typing import Any, Protocol

from pydantic import BaseModel, Field


class ToolDeclaration(BaseModel):
    """Function declaration sent to the provider, generated from the registry."""

    name: str
    description: str
    parameters: dict[str, Any]


class ContextBlock(BaseModel):
    """One sanitized context block for the prompt (retrieved chunk or tool result)."""

    block_id: str
    kind: str = "tool_result"  # tool_result | rag_chunk
    content: str


class LLMRequest(BaseModel):
    system_instruction: str
    user_brief: str
    context_blocks: list[ContextBlock] = Field(default_factory=list)
    tools: list[ToolDeclaration] = Field(default_factory=list)
    temperature: float | None = None
    max_output_tokens: int | None = None
    json_schema: dict[str, Any] | None = None
    prompt_version: str = "sipromo-v1"


class ToolCallProposal(BaseModel):
    """A tool call the model proposes; application validates before execution."""

    call_id: str
    tool_name: str
    arguments: dict[str, Any]


class ToolResultForModel(BaseModel):
    """Sanitized tool result appended to the conversation for the model."""

    call_id: str
    tool_name: str
    ok: bool
    data: dict[str, Any] | list[Any] | str
    error: str | None = None


class LLMTurn(BaseModel):
    """One model turn: either a final structured output or proposed tool calls."""

    final_output: dict[str, Any] | None = None
    tool_calls: list[ToolCallProposal] = Field(default_factory=list)
    usage: dict[str, Any] = Field(default_factory=dict)
    model_name: str = ""
    prompt_version: str = ""
    raw_response_hash: str = ""


class LLMPort(Protocol):
    async def generate_with_tools(self, request: LLMRequest) -> LLMTurn: ...

    async def continue_with_tool_results(
        self,
        request: LLMRequest,
        tool_results: list[ToolResultForModel],
    ) -> LLMTurn: ...

    async def repair_output(
        self, request: LLMRequest, invalid_raw: dict, errors: list[str]
    ) -> dict | None: ...

    @property
    def provider_name(self) -> str: ...

    @property
    def model_name(self) -> str: ...
