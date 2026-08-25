"""Tool executor port and shared tool types."""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import UTC
from typing import Any, Protocol
from uuid import UUID

from pydantic import BaseModel


class ValidatedToolCall(BaseModel):
    """A tool call validated by the registry against its Pydantic schema."""

    call_id: str
    tool_name: str
    arguments: dict[str, Any]
    round_index: int


@dataclass
class ExecutionContext:
    """Server-controlled context injected into tool execution."""

    umkm_id: UUID
    user_id: UUID | None
    role: str
    request_id: str
    generation_run_id: UUID | None = None
    timeout_seconds: float = 10.0
    max_rows: int = 50
    now: Any = None

    def __post_init__(self) -> None:
        if self.now is None:
            from datetime import datetime

            self.now = datetime.now(UTC)


@dataclass
class ToolResult:
    call_id: str
    tool_name: str
    status: str  # succeeded | failed | denied
    data: dict[str, Any] | list[Any] = field(default_factory=dict)
    error_code: str | None = None
    error_message: str | None = None
    duration_ms: int | None = None
    latency_ms: int = 0

    def for_model(self) -> dict[str, Any]:
        """Sanitized payload appended to the model context."""
        if self.status == "succeeded":
            return {
                "call_id": self.call_id,
                "tool_name": self.tool_name,
                "ok": True,
                "data": self.data,
            }
        return {
            "call_id": self.call_id,
            "tool_name": self.tool_name,
            "ok": False,
            "error": self.error_message or self.error_code or "tool failed",
        }

    def audit_summary(self) -> dict[str, Any]:
        return {
            "tool_name": self.tool_name,
            "status": self.status,
            "duration_ms": self.duration_ms or self.latency_ms,
            "error_code": self.error_code,
        }


class ToolExecutorPort(Protocol):
    async def execute(
        self, tool_call: ValidatedToolCall, context: ExecutionContext
    ) -> ToolResult: ...


class ToolRegistryPort(Protocol):
    def validate(self, tool_call: Any) -> ValidatedToolCall: ...

    def find(self, tool_name: str) -> Any: ...

    def allowed_tools(self, role: str) -> list[str]: ...

    def declarations(self) -> list[Any]: ...
