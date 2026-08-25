"""Deterministic claim-grounding and content-safety rules (domain service).

Implements section 27 of the blueprint: currency/percentage/date/stock
extraction, superlative and certification detection, product-name matching,
CTA vs inventory compatibility, evidence-id validation, external URL checks,
and competitor hashtag checks.
"""

from __future__ import annotations

import re
from collections.abc import Iterable
from dataclasses import dataclass, field

from sipromo.domain.entities.promotion_content import PromotionOutput

CURRENCY_RE = re.compile(
    r"(?<![A-Za-z])(Rp\s?[\d.,]+|IDR\s?[\d.,]+|USD\s?[\d.,]+|\$\s?[\d.,]+)(?!\w)", re.IGNORECASE
)
PERCENTAGE_RE = re.compile(r"\d+(?:[.,]\d+)?\s?%")
DATE_RE = re.compile(
    r"\b(\d{1,2}[/-]\d{1,2}[/-]\d{2,4}|\d{4}-\d{2}-\d{2}|\d{1,2}\s(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Des|Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)[a-z]*\s?\d{2,4})\b",
    re.IGNORECASE,
)
STOCK_RE = re.compile(r"\b(stok|stock|tersisa|sisa)\s*[:=]?\s*(\d+)\b", re.IGNORECASE)

SUPERLATIVE_TERMS = (
    "nomor satu",
    "no. 1",
    "no 1",
    "terbaik",
    "terlaris",
    "paling laris",
    "paling murah",
    "termurah",
    "tercepat",
    "terpercaya",
    "paling",
    "terjamin",
    "pasti berhasil",
    "100%",
    "best",
    "number one",
    "guaranteed",
    "top rated",
)

CERTIFICATION_TERMS = (
    "bpom",
    "halal",
    "sni",
    "iso 9001",
    "iso 22000",
    "organic",
    "organik",
    "bersertifikat",
    "certified",
    "merkuri",
    "bebas bahan kimia",
)

DISCOUNT_TERMS = ("diskon", "discount", "promo potongan", "hemat", "obral", "clearance", "sale")

BUY_CTA_TERMS = (
    "beli sekarang",
    "beli di",
    "order sekarang",
    "pesan sekarang",
    "checkout",
    "buy now",
    "order now",
    "add to cart",
    "keranjang",
    "langsung pesan",
)

EXTERNAL_URL_RE = re.compile(r"https?://\S+", re.IGNORECASE)


@dataclass
class ClaimCheckResult:
    violations: list[str] = field(default_factory=list)

    def add(self, message: str) -> None:
        self.violations.append(message)

    @property
    def ok(self) -> bool:
        return not self.violations


def extract_numeric_claims(text: str) -> set[str]:
    """Normalized numeric/currency claims found in copy, e.g. 'Rp 25.000'."""
    claims: set[str] = set()
    for match in CURRENCY_RE.finditer(text):
        # normalize thousands separators: 'Rp25.000' / 'Rp25,000' -> 'rp25000'
        amount = re.sub(r"[.,]", "", match.group(1))
        claims.add(re.sub(r"\s+", "", amount).lower())
    for match in PERCENTAGE_RE.finditer(text):
        claims.add(re.sub(r"\s+", "", match.group(0)).lower())
    for match in DATE_RE.finditer(text):
        claims.add(match.group(0).strip().lower())
    for match in STOCK_RE.finditer(text):
        claims.add(f"stock:{match.group(2)}")
    return claims


def has_superlative(text: str) -> bool:
    lowered = text.lower()
    return any(term in lowered for term in SUPERLATIVE_TERMS)


def has_certification_claim(text: str) -> bool:
    lowered = text.lower()
    return any(term in lowered for term in CERTIFICATION_TERMS)


def has_discount_claim(text: str) -> bool:
    lowered = text.lower()
    return any(term in lowered for term in DISCOUNT_TERMS)


def has_buy_cta(text: str) -> bool:
    lowered = text.lower()
    return any(term in lowered for term in BUY_CTA_TERMS)


def extract_external_urls(text: str) -> list[str]:
    return EXTERNAL_URL_RE.findall(text)


def _is_partially_grounded(claim: str, available: set[str]) -> bool:
    """A claim is grounded if most of its tokens appear inside one available claim."""
    tokens = [t for t in re.findall(r"[a-z0-9]+", claim) if len(t) >= 3]
    tokens = [t for t in tokens if t not in _STOPWORDS]
    if not tokens:
        return False
    needed = max(1, int(len(tokens) * 0.6))
    for available_claim in available:
        if sum(1 for t in tokens if t in available_claim) >= needed:
            return True
    return False


_STOPWORDS = frozenset(
    {
        "dengan",
        "untuk",
        "dari",
        "yang",
        "dan",
        "adalah",
        "kami",
        "kita",
        "anda",
        "ini",
        "itu",
        "di",
        "ke",
        "lokasi",
        "berlokasi",
        "terletak",
        "berada",
        "berpusat",
        "beralamat",
        "the",
        "and",
        "for",
        "with",
        "our",
        "your",
        "this",
        "that",
    }
)


class ClaimPolicy:
    """Deterministic grounding checks applied to a PromotionOutput."""

    def __init__(
        self,
        *,
        available_tool_claims: set[str],
        available_product_names: set[str],
        inventory_eligible: dict[str, bool],
        available_certifications: set[str],
        allowed_discounts: bool,
        evidence_ids: set[str],
        competitor_terms: Iterable[str],
        allow_external_urls: bool = False,
    ) -> None:
        self.available_tool_claims = available_tool_claims
        self.available_product_names = {n.strip().lower() for n in available_product_names if n}
        self.inventory_eligible = inventory_eligible
        self.available_certifications = {c.lower() for c in available_certifications}
        self.allowed_discounts = allowed_discounts
        self.evidence_ids = evidence_ids
        self.competitor_terms = {t.lower() for t in competitor_terms}
        self.allow_external_urls = allow_external_urls

    def validate(self, output: PromotionOutput) -> ClaimCheckResult:
        result = ClaimCheckResult()
        full_text = "\n".join(
            [output.title, output.primary_copy, output.caption, output.call_to_action]
        )

        for claim in output.claims:
            normalized = claim.strip().lower()
            if normalized not in self.available_tool_claims and not _is_partially_grounded(
                normalized, self.available_tool_claims
            ):
                result.add(f"claim not grounded in tool results: '{claim}'")

        numeric = extract_numeric_claims(full_text)
        for claim in numeric:
            if claim not in self.available_tool_claims and not claim.startswith("stock:"):
                result.add(f"numeric claim not grounded in tool results: '{claim}'")
            elif claim.startswith("stock:"):
                result.add(f"stock claim in copy is not permitted: '{claim}'")

        if has_superlative(full_text) and not any("superlative" in c for c in output.claims):
            result.add("superlative/unproven claim detected in copy")

        if has_certification_claim(full_text):
            for term in CERTIFICATION_TERMS:
                if term in full_text.lower():
                    if term not in self.available_certifications:
                        result.add(f"certification claim without evidence: '{term}'")
                    break

        if has_discount_claim(full_text) and not self.allowed_discounts:
            result.add("discount claim without supporting data")

        for name in self.available_product_names:
            if name and name in full_text.lower():
                break
        else:
            if self.available_product_names:
                result.add("no selected product name appears in copy")

        if not self.allow_external_urls:
            for url in extract_external_urls(full_text):
                result.add(f"external URL created by model: '{url}'")

        for evidence in output.evidence:
            if evidence.evidence_id not in self.evidence_ids:
                result.add(f"evidence id not available: '{evidence.evidence_id}'")

        for tag in output.hashtags:
            lowered = tag.lower().lstrip("#")
            for competitor in self.competitor_terms:
                if competitor in lowered:
                    result.add(f"hashtag references competitor brand: '{tag}'")
                    break

        return result

    @staticmethod
    def cta_compatible_with_inventory(call_to_action: str, any_eligible: bool) -> ClaimCheckResult:
        result = ClaimCheckResult()
        if has_buy_cta(call_to_action) and not any_eligible:
            result.add("purchase CTA used but no product is in-stock")
        return result
