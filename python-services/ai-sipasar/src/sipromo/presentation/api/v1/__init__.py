from fastapi import APIRouter

from sipromo.presentation.api.v1 import (
    approvals,
    assets,
    health,
    knowledge,
    promotions,
)

router = APIRouter()
router.include_router(health.router)
router.include_router(promotions.router)
router.include_router(knowledge.router)
router.include_router(assets.router)
router.include_router(approvals.router)
