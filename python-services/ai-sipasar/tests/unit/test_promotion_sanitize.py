"""Unit tests: copy sanitization (em/en dash ban) in generate promotion."""

from __future__ import annotations

from sipromo.application.use_cases.generate_promotion import _no_dash, _sanitize_output
from sipromo.domain.entities.promotion_content import PromotionOutput


def test_no_dash_replaces_em_and_en_dash() -> None:
    assert _no_dash("Renyah\u2014pedas") == "Renyah,pedas"
    assert _no_dash("Baru\u2013datang") == "Baru,datang"
    assert _no_dash("bersih") == "bersih"


def test_sanitize_output_strips_dashes_from_all_text_fields() -> None:
    output = PromotionOutput(
        title="Promo\u2014Seru",
        primary_copy="Coba keripik pedas\u2014enak sekali.",
        caption="Caption\u2013panjang",
        call_to_action="Beli\u2014sekarang",
        hashtags=["#Kopdes\u2013Promo", "#Aman"],
        claims=["harga rp 25000"],
    )

    clean = _sanitize_output(output)

    assert clean.title == "Promo,Seru"
    assert clean.primary_copy == "Coba keripik pedas,enak sekali."
    assert clean.caption == "Caption,panjang"
    assert clean.call_to_action == "Beli,sekarang"
    assert clean.hashtags == ["#Kopdes,Promo", "#Aman"]
    assert clean.claims == ["harga rp 25000"]


def test_placeholder_url_detection() -> None:
    from sipromo.application.use_cases.generate_promotion import _is_placeholder_url

    assert _is_placeholder_url("https://placehold.co/600x600/FF8A3D/FFFFFF.png?text=Batik")
    assert _is_placeholder_url("https://via.placeholder.com/600")
    assert _is_placeholder_url("https://cdn.example.com/img/placeholder.png")
    assert not _is_placeholder_url("https://cdn.shop.com/foto-produk-asli.jpg")
    assert not _is_placeholder_url("https://picsum.photos/seed/batik/600/600")
