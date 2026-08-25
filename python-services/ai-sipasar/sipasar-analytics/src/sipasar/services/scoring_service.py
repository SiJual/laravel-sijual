"""SiPasar — Market Potential Scoring service.

Implements PRD §9.3: combine competitor + geodemografi → market_potential_score.
Generates template-based narrative (deterministic, auditable).
"""

from __future__ import annotations

import logging

from sipasar.core.config import get_settings
from sipasar.models.domain import CompetitorAnalysisResult, GeodemografiResult, ScoringResult
from sipasar.utils.geo_utils import clamp, normalize_to_01

logger = logging.getLogger(__name__)

# ── Normalisation reference values ────────────────────────────────────────────

# Max expected population density (per km²) for normalization
_MAX_POP_DENSITY = 25_000.0
_MIN_POP_DENSITY = 500.0

# Max expected population estimate for demand proxy normalization
_MAX_POP_ESTIMATE = 500_000
_MIN_POP_ESTIMATE = 100


# ── Category fit heuristics ───────────────────────────────────────────────────

# How well a business category matches a consumer segment (0.0 – 1.0)
_CATEGORY_FIT_MATRIX: dict[str, dict[str, float]] = {
    "kuliner_kopi": {
        "pekerja_kantoran": 0.95,
        "pelajar_mahasiswa": 0.90,
        "residensial_menengah_atas": 0.80,
        "pekerja_dan_pedagang": 0.70,
        "permukiman_campuran": 0.65,
        "permukiman_umum": 0.55,
    },
    "kuliner_restoran": {
        "pekerja_kantoran": 0.85,
        "pelajar_mahasiswa": 0.80,
        "permukiman_campuran": 0.75,
        "permukiman_umum": 0.70,
        "pekerja_dan_pedagang": 0.70,
        "residensial_menengah_atas": 0.65,
    },
    "kuliner_warung": {
        "permukiman_campuran": 0.90,
        "permukiman_umum": 0.85,
        "pelajar_mahasiswa": 0.80,
        "pekerja_dan_pedagang": 0.75,
        "pekerja_kantoran": 0.60,
        "residensial_menengah_atas": 0.30,
    },
    "retail_fashion": {
        "residensial_menengah_atas": 0.90,
        "pelajar_mahasiswa": 0.85,
        "pekerja_kantoran": 0.75,
        "permukiman_campuran": 0.60,
        "permukiman_umum": 0.50,
        "pekerja_dan_pedagang": 0.55,
    },
    "retail_sembako": {
        "permukiman_campuran": 0.95,
        "permukiman_umum": 0.90,
        "pekerja_dan_pedagang": 0.75,
        "pelajar_mahasiswa": 0.70,
        "pekerja_kantoran": 0.50,
        "residensial_menengah_atas": 0.55,
    },
    "jasa_salon": {
        "residensial_menengah_atas": 0.90,
        "permukiman_campuran": 0.80,
        "permukiman_umum": 0.70,
        "pelajar_mahasiswa": 0.75,
        "pekerja_kantoran": 0.65,
        "pekerja_dan_pedagang": 0.60,
    },
    "jasa_laundry": {
        "pelajar_mahasiswa": 0.95,
        "permukiman_campuran": 0.80,
        "permukiman_umum": 0.75,
        "pekerja_kantoran": 0.65,
        "pekerja_dan_pedagang": 0.55,
        "residensial_menengah_atas": 0.40,
    },
}

_DEFAULT_CATEGORY_FIT = 0.60


def _get_category_fit(category: str, consumer_segment: str) -> float:
    """Look up category-to-consumer-segment fitness score."""
    cat_map = _CATEGORY_FIT_MATRIX.get(category)
    if cat_map is None:
        return _DEFAULT_CATEGORY_FIT
    return cat_map.get(consumer_segment, _DEFAULT_CATEGORY_FIT)


# ── Narrative generation (template-based) ─────────────────────────────────────

def _generate_narrative(
    label: str,
    score: float,
    comp_level: str,
    pop_estimate: int,
    economic_indicator: str,
    dominant_segment: str,
    category_fit: float,
) -> str:
    """Generate a human-readable insight narrative (template-based, deterministic)."""
    parts: list[str] = []

    # Opening
    label_text = {
        "tinggi": "[TINGGI] Potensi pasar **tinggi**",
        "sedang": "[SEDANG] Potensi pasar **sedang**",
        "rendah": "[RENDAH] Potensi pasar **rendah**",
    }.get(label, "Potensi pasar tidak diketahui")
    parts.append(f"{label_text} (skor: {score:.0%})")

    # Competition factor
    comp_texts = {
        "rendah": "tingkat kompetisi masih rendah sehingga peluang masuk pasar lebih terbuka",
        "sedang": "tingkat kompetisi moderat — persaingan ada namun masih ada ruang untuk pemain baru",
        "tinggi": "tingkat kompetisi tinggi — pasar sudah cukup padat, diferensiasi produk menjadi kunci",
    }
    parts.append(comp_texts.get(comp_level, ""))

    # Demand factor
    pop_str = f"{pop_estimate:,}".replace(",", ".")
    parts.append(f"estimasi populasi dalam radius sebesar ~{pop_str} jiwa")

    # Economic factor
    econ_texts = {
        "tinggi": "daya beli masyarakat sekitar tergolong tinggi",
        "menengah": "daya beli masyarakat sekitar berada di level menengah",
        "rendah": "daya beli masyarakat sekitar relatif rendah — perlu strategi harga yang kompetitif",
    }
    if economic_indicator in econ_texts:
        parts.append(econ_texts[economic_indicator])

    # Category fit factor
    if category_fit >= 0.80:
        parts.append(
            f"segmen konsumen dominan ({dominant_segment.replace('_', ' ')}) sangat sesuai dengan kategori usaha ini"
        )
    elif category_fit >= 0.60:
        parts.append(
            f"segmen konsumen dominan ({dominant_segment.replace('_', ' ')}) cukup sesuai dengan usaha ini"
        )
    else:
        parts.append(
            f"perlu validasi tambahan karena segmen dominan ({dominant_segment.replace('_', ' ')}) kurang linear dengan kategori usaha ini"
        )

    # Closing recommendation
    if label == "tinggi":
        parts.append("Lokasi ini layak dipertimbangkan untuk ekspansi atau pembukaan usaha baru.")
    elif label == "sedang":
        parts.append(
            "Analisis lebih lanjut disarankan sebelum memutuskan ekspansi. Pertimbangkan diferensiasi produk/layanan."
        )
    else:
        parts.append(
            "Pertimbangkan lokasi alternatif atau lakukan riset lebih dalam sebelum berinvestasi di lokasi ini."
        )

    return ". ".join(p.rstrip(".") for p in parts if p) + "."


# ── Main service ──────────────────────────────────────────────────────────────


class ScoringService:
    """Market potential scoring service (PRD §9.3)."""

    def __init__(self) -> None:
        self._settings = get_settings()

    def score(
        self,
        competitor_result: CompetitorAnalysisResult,
        geodemografi_result: GeodemografiResult,
        category: str,
    ) -> ScoringResult:
        """
        Compute market potential score.

        PRD §9.3 formula:
        market_potential_score = normalize(
            a1 * demand_proxy +
            a2 * purchasing_power_proxy +
            a3 * (1 - competition_score) +
            a4 * category_fit
        )
        """
        s = self._settings

        # ── Component 1: Demand proxy ─────────────────────────────────────────
        # Normalise population estimate and density, take average
        norm_pop_est = normalize_to_01(
            geodemografi_result.population_estimate, _MIN_POP_ESTIMATE, _MAX_POP_ESTIMATE
        )
        norm_pop_density = normalize_to_01(
            geodemografi_result.population_density_per_km2, _MIN_POP_DENSITY, _MAX_POP_DENSITY
        )
        demand_proxy = (norm_pop_est + norm_pop_density) / 2.0

        # ── Component 2: Purchasing power proxy ───────────────────────────────
        purchasing_power = geodemografi_result.purchasing_power_proxy

        # ── Component 3: Competition factor (inverted — lower comp = better) ──
        competition_factor = 1.0 - competitor_result.competition_score

        # ── Component 4: Category fit ─────────────────────────────────────────
        category_fit = _get_category_fit(
            category, geodemografi_result.dominant_consumer_segment
        )

        # ── Weighted sum ──────────────────────────────────────────────────────
        raw_score = (
            s.MKT_A1_DEMAND * demand_proxy
            + s.MKT_A2_PURCHASING_POWER * purchasing_power
            + s.MKT_A3_COMPETITION * competition_factor
            + s.MKT_A4_CATEGORY_FIT * category_fit
        )
        score = clamp(raw_score)

        # ── Label ─────────────────────────────────────────────────────────────
        if score >= s.MKT_THRESHOLD_HIGH:
            label = "tinggi"
        elif score >= s.MKT_THRESHOLD_MEDIUM:
            label = "sedang"
        else:
            label = "rendah"

        # ── Narrative ─────────────────────────────────────────────────────────
        narrative = _generate_narrative(
            label=label,
            score=score,
            comp_level=competitor_result.competition_level,
            pop_estimate=geodemografi_result.population_estimate,
            economic_indicator=geodemografi_result.economic_indicator,
            dominant_segment=geodemografi_result.dominant_consumer_segment,
            category_fit=category_fit,
        )

        logger.info(
            "Scoring done: demand=%.3f, purchasing_power=%.3f, competition_factor=%.3f, "
            "category_fit=%.3f → score=%.3f (%s)",
            demand_proxy,
            purchasing_power,
            competition_factor,
            category_fit,
            score,
            label,
        )

        return ScoringResult(
            score=round(score, 4),
            label=label,
            narrative=narrative,
            demand_proxy=round(demand_proxy, 4),
            purchasing_power_proxy=round(purchasing_power, 4),
            competition_factor=round(competition_factor, 4),
            category_fit=round(category_fit, 4),
        )
