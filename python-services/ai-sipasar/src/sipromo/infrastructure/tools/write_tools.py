"""Write tools. MVP: save_promotion_draft is app-controlled - the model cannot
invoke it from the generation loop; persistence always happens inside the
validated use case path (section 11.2, 18.1)."""

from __future__ import annotations

from pydantic import BaseModel

from sipromo.application.ports.tool_executor import ExecutionContext
from sipromo.infrastructure.tools.registry import Tool, ToolRegistry


class _NoArgs(BaseModel):
    pass


def register_write_tools(registry: ToolRegistry) -> None:
    async def _save_promotion_draft(_: dict, context: ExecutionContext) -> dict:
        return {
            "denied": True,
            "reason": (
                "Penyimpanan draf dilakukan otomatis oleh aplikasi setelah "
                "validasi grounding; tool ini tidak tersedia dari loop generasi."
            ),
        }

    registry.register(
        Tool(
            name="save_promotion_draft",
            description=(
                "Penyimpanan draf oleh aplikasi setelah validasi. Tidak dapat "
                "dipanggil langsung dari loop generasi."
            ),
            args_model=_NoArgs,
            handler=_save_promotion_draft,
            is_write=True,
        )
    )
