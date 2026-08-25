"""Tool policy: allowlists, argument limits, and authorization rules.

The server owns tool definitions and policies. The model only proposes calls;
this policy decides whether they may run for the actor in context.
"""

from __future__ import annotations

from sipromo.domain.exceptions import UnauthorizedActionError

READ_ONLY_TOOLS: frozenset[str] = frozenset(
    {
        "get_business_profile",
        "get_products",
        "get_inventory_eligibility",
        "get_market_summary",
        "get_competitor_summary",
        "get_sales_summary",
        "search_brand_knowledge",
    }
)

WRITE_TOOLS: frozenset[str] = frozenset({"save_promotion_draft"})

ALL_TOOLS: frozenset[str] = READ_ONLY_TOOLS | WRITE_TOOLS

# Tools never exposed to the model during the generation loop.
APP_ONLY_TOOLS: frozenset[str] = frozenset({"create_publish_job"})


class ToolPolicy:
    @staticmethod
    def is_read_only(tool_name: str) -> bool:
        return tool_name in READ_ONLY_TOOLS

    @staticmethod
    def is_allowed(tool_name: str) -> bool:
        return tool_name in ALL_TOOLS

    @staticmethod
    def allowed_for_role(tool_name: str, role: str) -> bool:
        if not ToolPolicy.is_allowed(tool_name):
            return False
        if tool_name in READ_ONLY_TOOLS:
            return role in {"owner", "staff", "viewer"}
        if tool_name in WRITE_TOOLS:
            return role in {"owner", "staff"}
        return False

    @staticmethod
    def authorized_tool_names(role: str) -> list[str]:
        return sorted(name for name in ALL_TOOLS if ToolPolicy.allowed_for_role(name, role))

    @staticmethod
    def authorize(tool_name: str, role: str) -> None:
        if not ToolPolicy.allowed_for_role(tool_name, role):
            raise UnauthorizedActionError(f"Tool '{tool_name}' not authorized for role '{role}'")
