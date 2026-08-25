"""Unit tests: domain value objects, claims policy, tenant policy,
content safety, tool argument sanitization, and prompt assembly.

These tests never touch the network or a database.
"""

from __future__ import annotations

import json
import uuid

import pytest
from pydantic import ValidationError

from sipromo.domain.services.claim_policy import ClaimPolicy
from sipromo.domain.value_objects.content_type import (
    ApprovalDecision,
    ContentType,
    DocumentStatus,
    Language,
    Objective,
    Platform,
    SourceKind,
    Tone,
)
from sipromo.domain.value_objects.promotion_brief import PromotionBrief

# ---------------------------------------------------------------- brief model


def test_promotion_brief_valid() -> None:
    brief = PromotionBrief(
        objective=Objective.AWARENESS,
        content_type=ContentType.SOCIAL_MEDIA,
        product_ids=[uuid.uuid4()],
        tone=Tone.FRIENDLY,
        language=Language.ID,
        target_audience="Gen Z",
        key_message="Keripik baru kami sudah tersedia",
        platform=Platform.INSTAGRAM,
    )
    assert brief.objective == "awareness"
    assert brief.key_message == "Keripik baru kami sudah tersedia"


def test_promotion_brief_requires_product() -> None:
    with pytest.raises(ValidationError):
        PromotionBrief(
            objective=Objective.AWARENESS,
            content_type=ContentType.SOCIAL_MEDIA,
            product_ids=[],
            tone=Tone.FRIENDLY,
            language=Language.ID,
            key_message="Promosi produk baru",
        )


def test_promotion_brief_rejects_blank_key_message() -> None:
    with pytest.raises(ValidationError):
        PromotionBrief(
            objective=Objective.AWARENESS,
            content_type=ContentType.SOCIAL_MEDIA,
            product_ids=[uuid.uuid4()],
            tone=Tone.FRIENDLY,
            language=Language.ID,
            key_message="   ",
        )


def test_promotion_brief_platform_defaults_to_generic() -> None:
    brief = PromotionBrief(
        objective=Objective.ENGAGEMENT,
        content_type=ContentType.SOCIAL_MEDIA,
        product_ids=[uuid.uuid4()],
        tone=Tone.PLAYFUL,
        key_message="Ajak temanmu ikut serta",
    )
    assert brief.platform == Platform.GENERIC


def test_promotion_brief_limits_constraints() -> None:
    with pytest.raises(ValidationError):
        PromotionBrief(
            objective=Objective.AWARENESS,
            content_type=ContentType.SOCIAL_MEDIA,
            product_ids=[uuid.uuid4()],
            tone=Tone.FRIENDLY,
            key_message="Promosi",
            constraints=[f"c{i}" for i in range(21)],
        )


# ------------------------------------------------------------- claim policy


def _policy(**kwargs) -> ClaimPolicy:
    defaults = dict(
        available_tool_claims=set(),
        available_product_names=set(),
        inventory_eligible={},
        available_certifications=set(),
        allowed_discounts=False,
        evidence_ids=set(),
        competitor_terms=set(),
    )
    defaults.update(kwargs)
    return ClaimPolicy(**defaults)


def test_claim_policy_grounding_accepts_grounded() -> None:
    from sipromo.domain.entities.promotion_content import PromotionOutput

    policy = _policy(
        available_tool_claims={"rp25000"},
        available_product_names={"keripik pedas"},
        inventory_eligible={"keripik pedas": True},
        allowed_discounts=False,
    )
    outcome = policy.validate(
        PromotionOutput(
            title="Keripik Pedas",
            primary_copy="Keripik pedas kami dijual Rp25.000 per bungkus.",
        )
    )
    assert outcome.ok is True


def test_claim_policy_normalizes_currency_separators() -> None:
    from sipromo.domain.entities.promotion_content import PromotionOutput

    policy = _policy(
        available_tool_claims={"rp350000"},
        available_product_names={"batik"},
    )
    outcome = policy.validate(
        PromotionOutput(
            title="Batik Tulis",
            primary_copy="Batik tulis berkualitas seharga Rp350.000 saja.",
        )
    )
    assert outcome.ok is True


def test_claim_policy_flags_price_without_evidence() -> None:
    from sipromo.domain.entities.promotion_content import PromotionOutput

    outcome = _policy(available_product_names={"keripik pedas"}).validate(
        PromotionOutput(
            title="Keripik Pedas",
            primary_copy="Keripik pedas dijual dengan harga Rp10.000 saja.",
        )
    )
    assert outcome.ok is False
    assert any("rp10000" in v for v in outcome.violations)


def test_claim_policy_flags_percentage() -> None:
    from sipromo.domain.entities.promotion_content import PromotionOutput

    outcome = _policy().validate(
        PromotionOutput(title="Diskon", primary_copy="Diskon 90% untuk semua produk.")
    )
    assert outcome.ok is False
    assert any("90%" in v for v in outcome.violations)


def test_claim_policy_flags_superlative_and_certification() -> None:
    from sipromo.domain.entities.promotion_content import PromotionOutput

    outcome = _policy().validate(
        PromotionOutput(
            title="Terbaik",
            primary_copy="Produk kami terbaik di Indonesia dan bersertifikat BPOM.",
        )
    )
    assert outcome.ok is False
    assert any("superlative" in v for v in outcome.violations)
    assert any("certification" in v for v in outcome.violations)


def test_claim_policy_known_facts_ground_claims() -> None:
    from sipromo.domain.entities.promotion_content import PromotionOutput

    policy = _policy(
        available_tool_claims={"rp25000", "keripik pedas tersedia"},
        available_product_names={"keripik pedas"},
        inventory_eligible={"keripik pedas": True},
    )
    outcome = policy.validate(
        PromotionOutput(
            title="Keripik Pedas",
            primary_copy="Keripik pedas Rp25.000 tersedia. Stok terbatas, cek katalog.",
            claims=["keripik pedas tersedia"],
        )
    )
    assert outcome.ok is True


def test_claim_policy_flags_unauthorized_evidence_id() -> None:
    from sipromo.domain.entities.promotion_content import PromotionOutput
    from sipromo.domain.value_objects.provenance import EvidenceItem

    outcome = _policy(evidence_ids={"evt-1"}).validate(
        PromotionOutput(
            title="X",
            primary_copy="Konten.",
            evidence=[
                EvidenceItem(evidence_id="evt-999", source_kind="tool_result", source_ref="t")
            ],
        )
    )
    assert outcome.ok is False
    assert any("evt-999" in v for v in outcome.violations)


def test_cta_compatible_with_inventory() -> None:
    blocked = ClaimPolicy.cta_compatible_with_inventory("Beli sekarang!", any_eligible=False)
    assert blocked.ok is False
    allowed = ClaimPolicy.cta_compatible_with_inventory("Beli sekarang!", any_eligible=True)
    assert allowed.ok is True


# ------------------------------------------------------------ content safety


def test_content_safety_blocks_ungrounded_copy() -> None:
    from sipromo.application.policies.content_safety import (
        ContentValidationContext,
        DeterministicContentPolicy,
    )
    from sipromo.domain.entities.promotion_content import PromotionOutput

    policy = DeterministicContentPolicy()
    violations = policy.validate(
        PromotionOutput(
            title="Promo",
            primary_copy="Diskon 90% untuk semua produk hari ini!",
        ),
        ContentValidationContext(allowed_discounts=False),
    )
    assert violations
    assert any("90%" in v for v in violations)


def test_content_safety_allows_grounded_copy() -> None:
    from sipromo.application.policies.content_safety import (
        ContentValidationContext,
        DeterministicContentPolicy,
    )
    from sipromo.domain.entities.promotion_content import PromotionOutput

    policy = DeterministicContentPolicy()
    violations = policy.validate(
        PromotionOutput(
            title="Keripik Pedas",
            primary_copy="Keripik pedas Rp25.000 per bungkus tersedia di katalog.",
        ),
        ContentValidationContext(
            available_tool_claims={"rp25000"},
            available_product_names={"keripik pedas"},
            inventory_eligible={"keripik pedas": True},
            evidence_ids=set(),
        ),
    )
    assert violations == []


# ------------------------------------------------------------ tenant policy


def test_tenant_policy_denies_foreign_umkm() -> None:
    from sipromo.application.policies.tenant_policy import TenantPolicy
    from sipromo.domain.exceptions import TenantMismatchError

    actor_umkm = uuid.uuid4()
    TenantPolicy.assert_owns(actor_umkm, actor_umkm, "content")
    with pytest.raises(TenantMismatchError):
        TenantPolicy.assert_owns(actor_umkm, uuid.uuid4(), "content")


# ----------------------------------------------------- tool arg sanitization


def test_sanitize_payload_removes_private_fields() -> None:
    from sipromo.infrastructure.tools.registry import sanitize_payload

    payload = {
        "name": "Test",
        "password": "secret",
        "brand_metadata": {"api_key": "abc", "ok": 1},
        "nested": {"api_secret": "abc", "keep": "y"},
        "phone": "0812",
    }
    cleaned = sanitize_payload(payload)
    assert cleaned["name"] == "Test"
    assert "password" not in cleaned
    assert "api_key" not in cleaned["brand_metadata"]
    assert "api_secret" not in cleaned["nested"]
    assert "phone" not in cleaned
    assert cleaned["brand_metadata"]["ok"] == 1
    assert cleaned["nested"]["keep"] == "y"


def test_sanitize_payload_passes_strings_through() -> None:
    """Tool arguments are validated as dicts; opaque strings are not parsed."""
    from sipromo.infrastructure.tools.registry import sanitize_payload

    raw = json.dumps({"secret_key": "x"})
    assert sanitize_payload(raw) == raw


# ------------------------------------------------------------ tool registry


def test_tool_registry_requires_unique_names() -> None:
    from pydantic import BaseModel

    from sipromo.infrastructure.tools.registry import Tool, ToolRegistry

    class Args(BaseModel):
        id: str

    registry = ToolRegistry()
    registry.register(Tool(name="dup", description="d", args_model=Args, handler=lambda **_: "ok"))
    with pytest.raises(ValueError):
        registry.register(
            Tool(name="dup", description="d", args_model=Args, handler=lambda **_: "ok")
        )


# ------------------------------------------------------------- enum wiring


def test_content_type_enums_serialize_as_strings() -> None:
    assert Tone.FRIENDLY.value == "friendly"
    assert Platform.INSTAGRAM.value == "instagram"
    assert Language.ID.value == "id"
    assert DocumentStatus.READY.value == "ready"
    assert ApprovalDecision.APPROVED.value == "approved"
    assert SourceKind.RAG_CHUNK.value == "rag_chunk"
