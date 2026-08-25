"""SiPasar — Analysis routes.

Endpoints:
    POST  /v1/analysis/run
    GET   /v1/analysis/{analysis_id}
    GET   /v1/analysis/history
    POST  /v1/analysis/{analysis_id}/rerun
"""

from __future__ import annotations

import logging
import uuid
from datetime import UTC, datetime

from fastapi import APIRouter, HTTPException, Query

from sipasar.core.logging import set_analysis_id
from sipasar.models.schemas import (
    AnalysisHistoryItem,
    AnalysisHistoryResponse,
    AnalysisResponse,
    CompetitorItemResponse,
    CompetitorResponse,
    GeodemografiResponse,
    MarketPotentialResponse,
    RerunAnalysisRequest,
    RunAnalysisRequest,
)
from sipasar.services.competitor_service import CompetitorService
from sipasar.services.geodemografi_service import GeodemografiService
from sipasar.services.scoring_service import ScoringService

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/v1/analysis", tags=["analysis"])

# ── In-memory store (replace with Supabase/DB in production) ─────────────────

_analysis_store: dict[str, AnalysisResponse] = {}


# ── Helper: run full analysis pipeline ───────────────────────────────────────


async def _run_pipeline(
    request: RunAnalysisRequest,
    analysis_id: uuid.UUID | None = None,
) -> AnalysisResponse:
    """Execute the full SiPasar analysis pipeline."""
    aid = analysis_id or uuid.uuid4()
    set_analysis_id(str(aid))

    logger.info(
        "Starting analysis %s: business_profile=%s, lat=%.6f, lon=%.6f, category=%s, radius=%dm",
        aid,
        request.business_profile_id,
        request.latitude,
        request.longitude,
        request.category,
        request.radius_meters,
    )

    competitor_svc = CompetitorService()
    geodemografi_svc = GeodemografiService()
    scoring_svc = ScoringService()

    try:
        # ── Step 1: Competitor Analysis ───────────────────────────────────────
        comp_result = await competitor_svc.analyze(
            lat=request.latitude,
            lon=request.longitude,
            category=request.category,
            radius_meters=request.radius_meters,
        )

        # ── Step 2: Geodemographic Analysis ───────────────────────────────────
        geo_result = await geodemografi_svc.analyze(
            lat=request.latitude,
            lon=request.longitude,
            radius_meters=request.radius_meters,
        )

        # ── Step 3: Market Potential Scoring ──────────────────────────────────
        score_result = scoring_svc.score(comp_result, geo_result, request.category)

    finally:
        await competitor_svc.aclose()

    # ── Build response ────────────────────────────────────────────────────────
    competitor_resp = CompetitorResponse(
        count=comp_result.count,
        avg_rating=comp_result.avg_rating,
        competition_level=comp_result.competition_level,
        competition_score=comp_result.competition_score,
        competition_density_per_km2=comp_result.competition_density_per_km2,
        competitors=[
            CompetitorItemResponse(
                name=c.name,
                category=c.category,
                rating=c.rating,
                distance_m=c.distance_meters,
                latitude=c.latitude,
                longitude=c.longitude,
            )
            for c in comp_result.competitors
        ],
    )

    geo_resp = GeodemografiResponse(
        population_estimate=geo_result.population_estimate,
        population_density_per_km2=geo_result.population_density_per_km2,
        economic_indicator=geo_result.economic_indicator,
        dominant_consumer_segment=geo_result.dominant_consumer_segment,
        area_name=geo_result.area_name,
    )

    mkt_resp = MarketPotentialResponse(
        score=score_result.score,
        label=score_result.label,
        narrative=score_result.narrative,
    )

    response = AnalysisResponse(
        analysis_id=aid,
        business_profile_id=request.business_profile_id,
        radius_meters=request.radius_meters,
        category=request.category,
        latitude=request.latitude,
        longitude=request.longitude,
        competitor=competitor_resp,
        geodemografi=geo_resp,
        market_potential=mkt_resp,
        created_at=datetime.now(UTC),
    )

    # Persist to in-memory store
    _analysis_store[str(aid)] = response
    logger.info("Analysis %s completed: label=%s, score=%.3f", aid, score_result.label, score_result.score)

    return response


# ── Endpoints ──────────────────────────────────────────────────────────────────


@router.post(
    "/run",
    response_model=AnalysisResponse,
    summary="Jalankan analisis pasar baru",
    description="Menjalankan analisis kompetitor + geodemografi + scoring potensi pasar untuk lokasi bisnis.",
)
async def run_analysis(request: RunAnalysisRequest) -> AnalysisResponse:
    return await _run_pipeline(request)


@router.get(
    "/history",
    response_model=AnalysisHistoryResponse,
    summary="Riwayat analisis per profil bisnis",
)
async def get_history(
    business_profile_id: uuid.UUID = Query(..., description="UUID profil bisnis"),
) -> AnalysisHistoryResponse:
    """Return analysis history for a given business profile."""
    analyses = [
        AnalysisHistoryItem(
            analysis_id=a.analysis_id,
            radius_meters=a.radius_meters,
            category=a.category,
            market_potential_label=a.market_potential.label,
            market_potential_score=a.market_potential.score,
            competition_level=a.competitor.competition_level,
            created_at=a.created_at,
        )
        for a in _analysis_store.values()
        if a.business_profile_id == business_profile_id
    ]
    analyses.sort(key=lambda x: x.created_at, reverse=True)

    return AnalysisHistoryResponse(
        business_profile_id=business_profile_id,
        analyses=analyses,
        total=len(analyses),
    )


@router.get(
    "/{analysis_id}",
    response_model=AnalysisResponse,
    summary="Detail hasil analisis",
)
async def get_analysis(analysis_id: uuid.UUID) -> AnalysisResponse:
    """Retrieve a stored analysis by ID."""
    record = _analysis_store.get(str(analysis_id))
    if record is None:
        raise HTTPException(status_code=404, detail=f"Analysis {analysis_id} not found.")
    return record


@router.post(
    "/{analysis_id}/rerun",
    response_model=AnalysisResponse,
    summary="Analisis ulang dengan parameter baru",
)
async def rerun_analysis(
    analysis_id: uuid.UUID,
    body: RerunAnalysisRequest | None = None,
) -> AnalysisResponse:
    """Re-run an existing analysis, optionally with a different radius."""
    original = _analysis_store.get(str(analysis_id))
    if original is None:
        raise HTTPException(status_code=404, detail=f"Analysis {analysis_id} not found.")

    new_radius = (body.radius_meters if body and body.radius_meters else None) or original.radius_meters

    new_request = RunAnalysisRequest(
        business_profile_id=original.business_profile_id,
        latitude=original.latitude,
        longitude=original.longitude,
        category=original.category,
        radius_meters=new_radius,
    )

    return await _run_pipeline(new_request)
