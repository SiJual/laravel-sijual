"""Content safety policy: port contract and deterministic adapter."""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Protocol

from sipromo.domain.entities.promotion_content import PromotionOutput
from sipromo.domain.services.claim_policy import ClaimPolicy


@dataclass
class ContentValidationContext:
    available_tool_claims: set[str] = field(default_factory=set)
    available_product_names: set[str] = field(default_factory=set)
    inventory_eligible: dict[str, bool] = field(default_factory=dict)
    available_certifications: set[str] = field(default_factory=set)
    allowed_discounts: bool = False
    evidence_ids: set[str] = field(default_factory=set)
    competitor_terms: list[str] = field(default_factory=list)


class ContentPolicyPort(Protocol):
    def validate(self, output: PromotionOutput, context: ContentValidationContext) -> list[str]:
        """Return list of violations (empty = pass)."""
        ...


class DeterministicContentPolicy(ContentPolicyPort):
    """Wraps the domain ClaimPolicy and reports plain violation strings."""

    def validate(self, output: PromotionOutput, context: ContentValidationContext) -> list[str]:
        policy = ClaimPolicy(
            available_tool_claims=context.available_tool_claims,
            available_product_names=context.available_product_names,
            inventory_eligible=context.inventory_eligible,
            available_certifications=context.available_certifications,
            allowed_discounts=context.allowed_discounts,
            evidence_ids=context.evidence_ids,
            competitor_terms=context.competitor_terms,
        )
        result = policy.validate(output)
        cta_result = ClaimPolicy.cta_compatible_with_inventory(
            output.call_to_action,
            any_eligible=any(context.inventory_eligible.values()),
        )
        return result.violations + cta_result.violations
