"""Read tools (MVP): business profile, products, inventory, market,
competitors, sales, and brand-knowledge search. Tenant is injected by the
executor context - never taken from model arguments."""

from __future__ import annotations

from datetime import date, timedelta
from uuid import UUID

from pydantic import BaseModel, Field, field_validator

from sipromo.application.ports.repositories import (
    BusinessReadRepository,
    InventoryEligibility,
    ProductFact,
)
from sipromo.application.ports.retriever import RetrievalQuery
from sipromo.application.ports.tool_executor import ExecutionContext
from sipromo.domain.value_objects.content_type import DocumentType
from sipromo.infrastructure.rag.hybrid_retriever import HybridRetriever
from sipromo.infrastructure.tools.registry import Tool, ToolRegistry


def product_facts_for_model(products: list[ProductFact]) -> list[dict]:
    """Product facts for the LLM: internal fields (image_url) are stripped
    so the model never cites them as claims; posters fetch images server-side."""
    return [
        {k: v for k, v in p.model_dump(mode="json").items() if k != "image_url"} for p in products
    ]


def inventory_eligibility_for_model(
    rows: list[InventoryEligibility],
) -> list[dict]:
    """Eligibility facts for the LLM: canonical status labels ('tersedia')
    instead of raw boolean keys the model might cite verbatim as claims."""
    return [
        {
            "product_id": str(r.product_id),
            "status": "tersedia" if r.eligible else "tidak tersedia",
            "reason": r.reason,
        }
        for r in rows
    ]


class GetProductsArgs(BaseModel):
    product_ids: list[UUID] = Field(min_length=1, max_length=10)


class GetInventoryArgs(BaseModel):
    product_ids: list[UUID] = Field(min_length=1, max_length=10)


class GetSalesArgs(BaseModel):
    product_ids: list[UUID] = Field(min_length=1, max_length=10)
    period_days: int = Field(default=30, ge=1, le=90)

    @field_validator("period_days")
    @classmethod
    def _at_least_one_day(cls, v: int) -> int:
        return max(1, v)


class GetCompetitorArgs(BaseModel):
    analysis_id: UUID | None = None


class SearchBrandKnowledgeArgs(BaseModel):
    query: str = Field(min_length=3, max_length=500)
    document_types: list[DocumentType] = Field(default_factory=list, max_length=6)


class NoArgs(BaseModel):
    pass


def register_read_tools(
    registry: ToolRegistry,
    *,
    business_repo: BusinessReadRepository,
    retriever: HybridRetriever,
) -> None:
    async def _get_business_profile(_: dict, context: ExecutionContext) -> dict:
        profile = await business_repo.get_business_profile(context.umkm_id)
        if profile is None:
            return {"name": None, "note": "profil bisnis belum tersedia"}
        return profile.model_dump(mode="json")

    async def _get_products(args: dict, context: ExecutionContext) -> list[dict]:
        products = await business_repo.get_products(context.umkm_id, args["product_ids"])
        return product_facts_for_model(products)

    async def _get_inventory_eligibility(args: dict, context: ExecutionContext) -> list[dict]:
        rows = await business_repo.get_inventory_eligibility(context.umkm_id, args["product_ids"])
        return inventory_eligibility_for_model(rows)

    async def _get_market_summary(_: dict, context: ExecutionContext) -> dict:
        summary = await business_repo.get_latest_market_summary(context.umkm_id)
        if summary is None:
            return {"note": "market analysis belum tersedia"}
        return summary.model_dump(mode="json")

    async def _get_competitor_summary(args: dict, context: ExecutionContext) -> dict:
        summary = await business_repo.get_competitor_summary(
            context.umkm_id, args.get("analysis_id")
        )
        if summary is None:
            return {"note": "data kompetitor belum tersedia"}
        return summary.model_dump(mode="json")

    async def _get_sales_summary(args: dict, context: ExecutionContext) -> list[dict]:
        end = date.today()
        start = end - timedelta(days=args["period_days"])
        rows = await business_repo.get_sales_summary(
            context.umkm_id, args["product_ids"], start, end
        )
        return [r.model_dump(mode="json") for r in rows]

    async def _search_brand_knowledge(args: dict, context: ExecutionContext) -> list[dict]:
        query = RetrievalQuery(
            query=args["query"][:2000],
            umkm_id=str(context.umkm_id),
            document_types=args.get("document_types") or [],
            top_k_vector=8,
            top_k_lexical=8,
            final_k=6,
            min_score=0.55,
            max_context_tokens=4000,
        )
        chunks = await retriever.retrieve(query)
        return [
            {
                "chunk_id": c.chunk_id,
                "document_id": c.document_id,
                "document_type": c.document_type,
                "content": c.content,
                "score": c.score,
            }
            for c in chunks
        ]

    tools = [
        Tool(
            name="get_business_profile",
            description=(
                "Ambil profil bisnis publik: nama usaha, tipe usaha, kota, "
                "provinsi, dan metadata merek. Tidak menyertakan kontak privat."
            ),
            args_model=NoArgs,
            handler=_get_business_profile,
        ),
        Tool(
            name="get_products",
            description=(
                "Ambil fakta produk: nama, kategori, harga, status, stok "
                "opsional, gambar, dan deskripsi. Hanya produk milik tenant aktif."
            ),
            args_model=GetProductsArgs,
            handler=_get_products,
        ),
        Tool(
            name="get_inventory_eligibility",
            description=(
                "Cek deterministik apakah produk aman dipromosikan berdasarkan "
                "status dan stok. Jika out of stock, jangan gunakan CTA beli."
            ),
            args_model=GetInventoryArgs,
            handler=_get_inventory_eligibility,
        ),
        Tool(
            name="get_market_summary",
            description="Ringkasan aman dari analisis pasar terbaru yang valid.",
            args_model=NoArgs,
            handler=_get_market_summary,
        ),
        Tool(
            name="get_competitor_summary",
            description="Agregat kompetitor pada analisis terkait. Jangan meniru merek kompetitor.",
            args_model=GetCompetitorArgs,
            handler=_get_competitor_summary,
        ),
        Tool(
            name="get_sales_summary",
            description=(
                "Agregat penjualan produk per periode (maksimal 90 hari). "
                "Jangan menampilkan transaksi mentah."
            ),
            args_model=GetSalesArgs,
            handler=_get_sales_summary,
        ),
        Tool(
            name="search_brand_knowledge",
            description=(
                "Cari pengetahuan naratif merek (brand guide, FAQ, catalog, "
                "contoh kampanye, kebijakan) dalam basis knowledge tenant."
            ),
            args_model=SearchBrandKnowledgeArgs,
            handler=_search_brand_knowledge,
        ),
    ]
    for tool in tools:
        registry.register(tool)
