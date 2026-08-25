"""Business facts repository over the legacy schema (products, profile,
market analyses, competitors, demographics, transactions)."""

from __future__ import annotations

from datetime import date
from uuid import UUID

from sqlalchemy import desc, func, select

from sipromo.application.ports.repositories import (
    BusinessProfile,
    BusinessReadRepository,
    CompetitorSummary,
    InventoryEligibility,
    MarketSummary,
    ProductFact,
    SalesSummary,
)
from sipromo.infrastructure.db.models.legacy import (
    Competitor,
    MarketAnalysis,
    Product,
    Transaction,
    UmkmProfile,
)
from sipromo.infrastructure.db.session import get_current_session

OUT_OF_STOCK_STATUSES = {"out_of_stock", "inactive", "discontinued", "archived"}


class BusinessRepository(BusinessReadRepository):
    async def get_business_profile(self, umkm_id: UUID) -> BusinessProfile | None:
        session = get_current_session()
        result = await session.execute(select(UmkmProfile).where(UmkmProfile.id == umkm_id))
        profile = result.scalar_one_or_none()
        if profile is None:
            return None
        return BusinessProfile(
            name=profile.business_name,
            business_type=profile.business_type,
            city=profile.city,
            province=profile.province,
            brand_metadata={},
        )

    async def get_products(self, umkm_id: UUID, product_ids: list[UUID]) -> list[ProductFact]:
        session = get_current_session()
        result = await session.execute(
            select(Product).where(
                Product.umkm_id == umkm_id,
                Product.id.in_(product_ids),
            )
        )
        products = result.scalars().all()
        return [
            ProductFact(
                product_id=p.id,
                name=p.name,
                category=p.category,
                price=p.price,
                status=p.status,
                stock_level=p.stock_level,
                image_url=p.image_url,
                description=p.description,
            )
            for p in products
        ]

    async def get_inventory_eligibility(
        self, umkm_id: UUID, product_ids: list[UUID]
    ) -> list[InventoryEligibility]:
        session = get_current_session()
        result = await session.execute(
            select(Product).where(
                Product.umkm_id == umkm_id,
                Product.id.in_(product_ids),
            )
        )
        eligibility: list[InventoryEligibility] = []
        for product in result.scalars().all():
            if product.status in OUT_OF_STOCK_STATUSES:
                eligibility.append(
                    InventoryEligibility(
                        product_id=product.id,
                        eligible=False,
                        reason=f"product status '{product.status}'",
                    )
                )
            elif product.stock_level is not None and product.stock_level <= 0:
                eligibility.append(
                    InventoryEligibility(
                        product_id=product.id,
                        eligible=False,
                        reason="stock_level is zero",
                    )
                )
            else:
                eligibility.append(
                    InventoryEligibility(product_id=product.id, eligible=True, reason="in stock")
                )
        return eligibility

    async def get_latest_market_summary(self, umkm_id: UUID) -> MarketSummary | None:
        session = get_current_session()
        result = await session.execute(
            select(MarketAnalysis)
            .where(
                MarketAnalysis.umkm_id == umkm_id,
                MarketAnalysis.status == "completed",
                (MarketAnalysis.expires_at.is_(None)) | (MarketAnalysis.expires_at > func.now()),
            )
            .order_by(desc(MarketAnalysis.created_at))
            .limit(1)
        )
        analysis = result.scalar_one_or_none()
        if analysis is None:
            return None
        data = analysis.analysis_data or {}
        return MarketSummary(
            analysis_id=analysis.id,
            title=analysis.location_query or "market analysis",
            summary=str(data.get("summary", "")),
            demographic_data=analysis.demographic_data or {},
        )

    async def get_competitor_summary(
        self, umkm_id: UUID, analysis_id: UUID | None = None
    ) -> CompetitorSummary | None:
        session = get_current_session()
        query = select(Competitor)
        if analysis_id is not None:
            query = query.where(Competitor.analysis_id == analysis_id)
        query = query.order_by(desc(Competitor.created_at)).limit(20)
        result = await session.execute(query)
        competitors = result.scalars().all()
        if not competitors:
            return None
        return CompetitorSummary(
            analysis_id=competitors[0].analysis_id or analysis_id or UUID(int=0),
            competitors=[{"name": c.name, "data": c.scraped_data or {}} for c in competitors],
        )

    async def get_sales_summary(
        self,
        umkm_id: UUID,
        product_ids: list[UUID],
        start: date,
        end: date,
    ) -> list[SalesSummary]:
        session = get_current_session()
        result = await session.execute(
            select(
                func.coalesce(func.sum(Transaction.amount), 0).label("total_revenue"),
                func.count(Transaction.id).label("total_units"),
                func.count(Transaction.id).label("transaction_count"),
            ).where(
                Transaction.umkm_id == umkm_id,
                Transaction.transaction_date >= start,
                Transaction.transaction_date <= end,
            )
        )
        return [
            SalesSummary(
                period_start=start,
                period_end=end,
                product_id=row.product_id,
                total_revenue=int(row.total_revenue),
                total_units=int(row.total_units),
                transaction_count=int(row.transaction_count),
            )
            for row in result.all()
        ]

    async def get_competitor_terms(self, umkm_id: UUID) -> list[str]:
        session = get_current_session()
        result = await session.execute(select(Competitor.name))
        return [name for (name,) in result.all() if name]
