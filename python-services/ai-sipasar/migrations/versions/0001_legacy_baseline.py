"""Baseline: legacy schema reconstruction (users, umkm_profiles, products,
content_assets, market_analyses, competitors, demographics, transactions,
reports, publish_jobs).

Column definitions mirror the production Neon schema exactly (no foreign
keys, timezone-naive legacy timestamps, legacy defaults). On an existing
Neon deployment the tables already exist and this migration is a no-op
(introspection guard). On an empty database this creates the tables the
application expects. No destructive changes are ever made to these tables -
new capabilities live in additive migrations only.

Revision ID: 0001_legacy_baseline
Revises:
Create Date: 2026-08-17
"""

from __future__ import annotations

import sqlalchemy as sa
from alembic import op
from sqlalchemy.dialects.postgresql import JSONB, UUID

revision: str = "0001_legacy_baseline"
down_revision: str | None = None
branch_labels: str | tuple[str, ...] | None = None
depends_on: str | tuple[str, ...] | None = None


def _legacy_tables_exist() -> bool:
    bind = op.get_bind()
    inspector = sa.inspect(bind)
    return "users" in inspector.get_table_names()


def upgrade() -> None:
    if _legacy_tables_exist():
        return

    op.create_table(
        "users",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("email", sa.String(255), nullable=False, unique=True),
        sa.Column("full_name", sa.String(255), nullable=True),
        sa.Column("phone", sa.String(255), nullable=True),
        sa.Column("avatar_url", sa.String(255), nullable=True),
        sa.Column("role", sa.String(255), nullable=False, server_default=sa.text("'owner'")),
        sa.Column("password", sa.String(255), nullable=False),
        sa.Column("remember_token", sa.String(100), nullable=True),
        sa.Column("email_verified_at", sa.DateTime(), nullable=True),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
    )
    op.create_table(
        "umkm_profiles",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("user_id", UUID(as_uuid=True), nullable=False, unique=True),
        sa.Column("business_name", sa.String(255), nullable=False),
        sa.Column("business_type", sa.String(255), nullable=True),
        sa.Column("address", sa.Text(), nullable=True),
        sa.Column("city", sa.String(255), nullable=True),
        sa.Column("province", sa.String(255), nullable=True),
        sa.Column("latitude", sa.Float(), nullable=True),
        sa.Column("longitude", sa.Float(), nullable=True),
        sa.Column("phone", sa.String(255), nullable=True),
        sa.Column("logo_url", sa.String(255), nullable=True),
        sa.Column(
            "profile_completeness", sa.Integer(), nullable=False, server_default=sa.text("0")
        ),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
    )
    op.create_table(
        "products",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("umkm_id", UUID(as_uuid=True), nullable=False),
        sa.Column("name", sa.String(255), nullable=False),
        sa.Column("sku", sa.String(255), nullable=True),
        sa.Column("category", sa.String(255), nullable=True),
        sa.Column("price", sa.BigInteger(), nullable=False, server_default=sa.text("0")),
        sa.Column("stock_level", sa.Integer(), nullable=False, server_default=sa.text("0")),
        sa.Column("status", sa.String(255), nullable=False, server_default=sa.text("'in_stock'")),
        sa.Column("image_url", sa.String(255), nullable=True),
        sa.Column("description", sa.Text(), nullable=True),
        sa.Column("low_stock_threshold", sa.Integer(), nullable=False, server_default=sa.text("5")),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
    )
    op.create_index("products_umkm_id_category_index", "products", ["umkm_id", "category"])
    op.create_table(
        "content_assets",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("umkm_id", UUID(as_uuid=True), nullable=False),
        sa.Column("title", sa.String(255), nullable=True),
        sa.Column("content_type", sa.String(255), nullable=False),
        sa.Column("prompt", sa.Text(), nullable=True),
        sa.Column("generated_text", sa.Text(), nullable=True),
        sa.Column("generated_image_url", sa.String(255), nullable=True),
        sa.Column("caption", sa.Text(), nullable=True),
        sa.Column("hashtags", sa.Text(), nullable=True),
        sa.Column("brand_metadata", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column("tone", sa.String(255), nullable=True),
        sa.Column("style", sa.String(255), nullable=True),
        sa.Column("version", sa.Integer(), nullable=False, server_default=sa.text("1")),
        sa.Column("status", sa.String(255), nullable=False, server_default=sa.text("'draft'")),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
    )
    op.create_index("content_assets_umkm_id_status_index", "content_assets", ["umkm_id", "status"])
    op.create_table(
        "market_analyses",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("umkm_id", UUID(as_uuid=True), nullable=False),
        sa.Column("location_query", sa.Text(), nullable=True),
        sa.Column("latitude", sa.Float(), nullable=True),
        sa.Column("longitude", sa.Float(), nullable=True),
        sa.Column("radius_km", sa.Float(), nullable=False, server_default=sa.text("1")),
        sa.Column("market_fit_score", sa.Integer(), nullable=False, server_default=sa.text("0")),
        sa.Column("analysis_data", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column("demographic_data", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column("status", sa.String(255), nullable=False, server_default=sa.text("'completed'")),
        sa.Column("expires_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
    )
    op.create_index("market_analyses_umkm_id_index", "market_analyses", ["umkm_id"])
    op.create_table(
        "competitors",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("analysis_id", UUID(as_uuid=True), nullable=False),
        sa.Column("name", sa.String(255), nullable=False),
        sa.Column("business_type", sa.String(255), nullable=True),
        sa.Column("address", sa.Text(), nullable=True),
        sa.Column("latitude", sa.Float(), nullable=True),
        sa.Column("longitude", sa.Float(), nullable=True),
        sa.Column("rating", sa.Float(), nullable=False, server_default=sa.text("0")),
        sa.Column("review_count", sa.Integer(), nullable=False, server_default=sa.text("0")),
        sa.Column("sentiment", sa.String(255), nullable=False, server_default=sa.text("'neutral'")),
        sa.Column("scraped_data", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
    )
    op.create_index("competitors_analysis_id_index", "competitors", ["analysis_id"])
    op.create_table(
        "demographics",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("umkm_id", UUID(as_uuid=True), nullable=False),
        sa.Column("analysis_id", UUID(as_uuid=True), nullable=True),
        sa.Column("area_name", sa.String(255), nullable=False),
        sa.Column("population_data", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column("income_data", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column("age_distribution", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column("data_source", sa.String(255), nullable=False, server_default=sa.text("'bps'")),
        sa.Column(
            "fetched_at",
            sa.DateTime(timezone=True),
            nullable=False,
            server_default=sa.text("CURRENT_TIMESTAMP"),
        ),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
    )
    op.create_index("demographics_umkm_id_index", "demographics", ["umkm_id"])
    op.create_index("demographics_analysis_id_index", "demographics", ["analysis_id"])
    op.create_table(
        "transactions",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("umkm_id", UUID(as_uuid=True), nullable=False),
        sa.Column("outlet_id", UUID(as_uuid=True), nullable=True),
        sa.Column("category_id", UUID(as_uuid=True), nullable=True),
        sa.Column("type", sa.String(255), nullable=False),
        sa.Column("amount", sa.BigInteger(), nullable=False, server_default=sa.text("0")),
        sa.Column("description", sa.Text(), nullable=True),
        sa.Column("notes", sa.Text(), nullable=True),
        sa.Column("source", sa.String(255), nullable=False, server_default=sa.text("'manual'")),
        sa.Column(
            "payment_method", sa.String(255), nullable=False, server_default=sa.text("'cash'")
        ),
        sa.Column("merchant_name", sa.String(255), nullable=True),
        sa.Column("ai_metadata", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column("is_verified", sa.Boolean(), nullable=False, server_default=sa.text("true")),
        sa.Column(
            "transaction_date", sa.Date(), nullable=False, server_default=sa.text("'2026-08-15'")
        ),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
    )
    op.create_index("transactions_category_id_index", "transactions", ["category_id"])
    op.create_index(
        "transactions_umkm_id_outlet_id_transaction_date_index",
        "transactions",
        ["umkm_id", "outlet_id", "transaction_date"],
    )
    op.create_table(
        "reports",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("umkm_id", UUID(as_uuid=True), nullable=False),
        sa.Column("type", sa.String(255), nullable=False),
        sa.Column("period_start", sa.Date(), nullable=False),
        sa.Column("period_end", sa.Date(), nullable=False),
        sa.Column("data", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column("file_url", sa.String(255), nullable=True),
        sa.Column("total_income", sa.BigInteger(), nullable=False, server_default=sa.text("0")),
        sa.Column("total_expense", sa.BigInteger(), nullable=False, server_default=sa.text("0")),
        sa.Column("net_profit", sa.BigInteger(), nullable=False, server_default=sa.text("0")),
        sa.Column("transaction_count", sa.Integer(), nullable=False, server_default=sa.text("0")),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
    )
    op.create_index("reports_umkm_id_index", "reports", ["umkm_id"])
    op.create_index(
        "reports_umkm_id_type_period_start_index", "reports", ["umkm_id", "type", "period_start"]
    )
    op.create_table(
        "publish_jobs",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column("content_id", UUID(as_uuid=True), nullable=False),
        sa.Column("platform", sa.String(255), nullable=False),
        sa.Column("status", sa.String(255), nullable=False, server_default=sa.text("'scheduled'")),
        sa.Column(
            "platform_response", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")
        ),
        sa.Column("scheduled_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("published_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
    )
    op.create_index("publish_jobs_content_id_index", "publish_jobs", ["content_id"])

    # --- Auxiliary tables (mirrors production Neon schema) ---
    op.create_table(
        "cache",
        sa.Column("key", sa.String(255), nullable=False),
        sa.Column("value", sa.Text(), nullable=False),
        sa.Column("expiration", sa.BigInteger(), nullable=False),
        sa.PrimaryKeyConstraint("key"),
    )
    op.create_index("cache_expiration_index", "cache", ["expiration"])
    op.create_table(
        "cache_locks",
        sa.Column("key", sa.String(255), nullable=False),
        sa.Column("owner", sa.String(255), nullable=False),
        sa.Column("expiration", sa.BigInteger(), nullable=False),
        sa.PrimaryKeyConstraint("key"),
    )
    op.create_index("cache_locks_expiration_index", "cache_locks", ["expiration"])
    op.create_table(
        "failed_jobs",
        sa.Column("id", sa.BigInteger(), autoincrement=True, nullable=False),
        sa.Column("uuid", sa.String(255), nullable=False),
        sa.Column("connection", sa.String(255), nullable=False),
        sa.Column("queue", sa.String(255), nullable=False),
        sa.Column("payload", sa.Text(), nullable=False),
        sa.Column("exception", sa.Text(), nullable=False),
        sa.Column(
            "failed_at", sa.DateTime(), server_default=sa.text("CURRENT_TIMESTAMP"), nullable=False
        ),
        sa.PrimaryKeyConstraint("id"),
        sa.UniqueConstraint("uuid"),
    )
    op.create_index(
        "failed_jobs_connection_queue_failed_at_index",
        "failed_jobs",
        ["connection", "queue", "failed_at"],
    )
    op.create_table(
        "job_batches",
        sa.Column("id", sa.String(255), nullable=False),
        sa.Column("name", sa.String(255), nullable=False),
        sa.Column("total_jobs", sa.Integer(), nullable=False),
        sa.Column("pending_jobs", sa.Integer(), nullable=False),
        sa.Column("failed_jobs", sa.Integer(), nullable=False),
        sa.Column("failed_job_ids", sa.Text(), nullable=False),
        sa.Column("options", sa.Text(), nullable=True),
        sa.Column("cancelled_at", sa.Integer(), nullable=True),
        sa.Column("created_at", sa.Integer(), nullable=False),
        sa.Column("finished_at", sa.Integer(), nullable=True),
        sa.PrimaryKeyConstraint("id"),
    )
    op.create_table(
        "jobs",
        sa.Column("id", sa.BigInteger(), autoincrement=True, nullable=False),
        sa.Column("queue", sa.String(255), nullable=False),
        sa.Column("payload", sa.Text(), nullable=False),
        sa.Column("attempts", sa.SmallInteger(), nullable=False),
        sa.Column("reserved_at", sa.Integer(), nullable=True),
        sa.Column("available_at", sa.Integer(), nullable=False),
        sa.Column("created_at", sa.Integer(), nullable=False),
        sa.PrimaryKeyConstraint("id"),
    )
    op.create_index("jobs_queue_index", "jobs", ["queue"])
    op.create_table(
        "migrations",
        sa.Column("id", sa.Integer(), autoincrement=True, nullable=False),
        sa.Column("migration", sa.String(255), nullable=False),
        sa.Column("batch", sa.Integer(), nullable=False),
        sa.PrimaryKeyConstraint("id"),
    )
    op.create_table(
        "password_reset_tokens",
        sa.Column("email", sa.String(255), nullable=False),
        sa.Column("token", sa.String(255), nullable=False),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint("email"),
    )
    op.create_table(
        "sessions",
        sa.Column("id", sa.String(255), nullable=False),
        sa.Column("user_id", UUID(as_uuid=True), nullable=True),
        sa.Column("ip_address", sa.String(45), nullable=True),
        sa.Column("user_agent", sa.Text(), nullable=True),
        sa.Column("payload", sa.Text(), nullable=False),
        sa.Column("last_activity", sa.Integer(), nullable=False),
        sa.PrimaryKeyConstraint("id"),
    )
    op.create_index("sessions_last_activity_index", "sessions", ["last_activity"])
    op.create_index("sessions_user_id_index", "sessions", ["user_id"])
    op.create_table(
        "categories",
        sa.Column("id", UUID(as_uuid=True), nullable=False),
        sa.Column("umkm_id", UUID(as_uuid=True), nullable=True),
        sa.Column("name", sa.String(255), nullable=False),
        sa.Column("type", sa.String(255), nullable=False),
        sa.Column("icon", sa.String(255), nullable=True),
        sa.Column("sort_order", sa.Integer(), nullable=False, server_default=sa.text("0")),
        sa.Column("is_system", sa.Boolean(), nullable=False, server_default=sa.text("false")),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint("id"),
        sa.ForeignKeyConstraint(["umkm_id"], ["umkm_profiles.id"], ondelete="CASCADE"),
        sa.CheckConstraint(
            "((type)::text = ANY ((ARRAY['income'::character varying, 'expense'::character varying])::text[]))",
            name="categories_type_check",
        ),
    )
    op.create_index("categories_umkm_id_index", "categories", ["umkm_id"])
    op.create_table(
        "invites",
        sa.Column("id", UUID(as_uuid=True), nullable=False),
        sa.Column("umkm_id", UUID(as_uuid=True), nullable=False),
        sa.Column("invited_by", UUID(as_uuid=True), nullable=False),
        sa.Column("email", sa.String(255), nullable=False),
        sa.Column("role", sa.String(255), nullable=False, server_default=sa.text("'staff'")),
        sa.Column("status", sa.String(255), nullable=False, server_default=sa.text("'pending'")),
        sa.Column("token", sa.String(255), nullable=False),
        sa.Column("expires_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint("id"),
        sa.UniqueConstraint("token"),
        sa.ForeignKeyConstraint(["invited_by"], ["users.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["umkm_id"], ["umkm_profiles.id"], ondelete="CASCADE"),
        sa.CheckConstraint(
            "((role)::text = ANY ((ARRAY['staff'::character varying, 'viewer'::character varying])::text[]))",
            name="invites_role_check",
        ),
        sa.CheckConstraint(
            "((status)::text = ANY ((ARRAY['pending'::character varying, 'accepted'::character varying, 'expired'::character varying])::text[]))",
            name="invites_status_check",
        ),
    )
    op.create_index("invites_umkm_id_index", "invites", ["umkm_id"])
    op.create_table(
        "outlets",
        sa.Column("id", UUID(as_uuid=True), nullable=False),
        sa.Column("umkm_id", UUID(as_uuid=True), nullable=False),
        sa.Column("name", sa.String(255), nullable=False),
        sa.Column("address", sa.Text(), nullable=True),
        sa.Column("latitude", sa.Float(), nullable=True),
        sa.Column("longitude", sa.Float(), nullable=True),
        sa.Column("is_primary", sa.Boolean(), nullable=False, server_default=sa.text("false")),
        sa.Column("created_at", sa.DateTime(), nullable=True),
        sa.Column("updated_at", sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint("id"),
        sa.ForeignKeyConstraint(["umkm_id"], ["umkm_profiles.id"], ondelete="CASCADE"),
    )
    op.create_index("outlets_umkm_id_index", "outlets", ["umkm_id"])

    # --- Foreign keys + check constraints (mirrors production Neon) ---
    op.create_foreign_key(
        "competitors_analysis_id_foreign",
        "competitors",
        "market_analyses",
        ["analysis_id"],
        ["id"],
        ondelete="CASCADE",
    )
    op.create_check_constraint(
        "competitors_sentiment_check",
        "competitors",
        "((sentiment)::text = ANY ((ARRAY['positive'::character varying, 'neutral'::character varying, 'negative'::character varying])::text[]))",
    )
    op.create_foreign_key(
        "content_assets_umkm_id_foreign",
        "content_assets",
        "umkm_profiles",
        ["umkm_id"],
        ["id"],
        ondelete="CASCADE",
    )
    op.create_check_constraint(
        "content_assets_content_type_check",
        "content_assets",
        "((content_type)::text = ANY ((ARRAY['social_media'::character varying, 'ad_copy'::character varying, 'blog_post'::character varying, 'email'::character varying])::text[]))",
    )
    op.create_check_constraint(
        "content_assets_status_check",
        "content_assets",
        "((status)::text = ANY ((ARRAY['draft'::character varying, 'published'::character varying, 'archived'::character varying])::text[]))",
    )
    op.create_foreign_key(
        "demographics_umkm_id_foreign",
        "demographics",
        "umkm_profiles",
        ["umkm_id"],
        ["id"],
        ondelete="CASCADE",
    )
    op.create_foreign_key(
        "demographics_analysis_id_foreign",
        "demographics",
        "market_analyses",
        ["analysis_id"],
        ["id"],
        ondelete="CASCADE",
    )
    op.create_check_constraint(
        "demographics_data_source_check",
        "demographics",
        "((data_source)::text = ANY ((ARRAY['bps'::character varying, 'osm'::character varying])::text[]))",
    )
    op.create_foreign_key(
        "market_analyses_umkm_id_foreign",
        "market_analyses",
        "umkm_profiles",
        ["umkm_id"],
        ["id"],
        ondelete="CASCADE",
    )
    op.create_check_constraint(
        "market_analyses_status_check",
        "market_analyses",
        "((status)::text = ANY ((ARRAY['pending'::character varying, 'processing'::character varying, 'completed'::character varying, 'failed'::character varying])::text[]))",
    )
    op.create_foreign_key(
        "products_umkm_id_foreign",
        "products",
        "umkm_profiles",
        ["umkm_id"],
        ["id"],
        ondelete="CASCADE",
    )
    op.create_check_constraint(
        "products_category_check",
        "products",
        "((category)::text = ANY ((ARRAY['textiles'::character varying, 'handicrafts'::character varying, 'food_bev'::character varying, 'services'::character varying, 'other'::character varying])::text[]))",
    )
    op.create_check_constraint(
        "products_status_check",
        "products",
        "((status)::text = ANY ((ARRAY['in_stock'::character varying, 'low_stock'::character varying, 'out_of_stock'::character varying])::text[]))",
    )
    op.create_foreign_key(
        "publish_jobs_content_id_foreign",
        "publish_jobs",
        "content_assets",
        ["content_id"],
        ["id"],
        ondelete="CASCADE",
    )
    op.create_check_constraint(
        "publish_jobs_platform_check",
        "publish_jobs",
        "((platform)::text = ANY ((ARRAY['instagram'::character varying, 'facebook'::character varying])::text[]))",
    )
    op.create_check_constraint(
        "publish_jobs_status_check",
        "publish_jobs",
        "((status)::text = ANY ((ARRAY['scheduled'::character varying, 'publishing'::character varying, 'published'::character varying, 'failed'::character varying])::text[]))",
    )
    op.create_foreign_key(
        "reports_umkm_id_foreign",
        "reports",
        "umkm_profiles",
        ["umkm_id"],
        ["id"],
        ondelete="CASCADE",
    )
    op.create_check_constraint(
        "reports_type_check",
        "reports",
        "((type)::text = ANY ((ARRAY['daily'::character varying, 'weekly'::character varying, 'monthly'::character varying])::text[]))",
    )
    op.create_foreign_key(
        "transactions_category_id_foreign",
        "transactions",
        "categories",
        ["category_id"],
        ["id"],
        ondelete="SET NULL",
    )
    op.create_foreign_key(
        "transactions_outlet_id_foreign",
        "transactions",
        "outlets",
        ["outlet_id"],
        ["id"],
        ondelete="SET NULL",
    )
    op.create_foreign_key(
        "transactions_umkm_id_foreign",
        "transactions",
        "umkm_profiles",
        ["umkm_id"],
        ["id"],
        ondelete="CASCADE",
    )
    op.create_check_constraint(
        "transactions_source_check",
        "transactions",
        "((source)::text = ANY ((ARRAY['voice'::character varying, 'manual'::character varying, 'qris'::character varying])::text[]))",
    )
    op.create_check_constraint(
        "transactions_type_check",
        "transactions",
        "((type)::text = ANY ((ARRAY['income'::character varying, 'expense'::character varying])::text[]))",
    )
    op.create_foreign_key(
        "umkm_profiles_user_id_foreign",
        "umkm_profiles",
        "users",
        ["user_id"],
        ["id"],
        ondelete="CASCADE",
    )
    op.create_check_constraint(
        "users_role_check",
        "users",
        "((role)::text = ANY ((ARRAY['owner'::character varying, 'staff'::character varying, 'viewer'::character varying])::text[]))",
    )


def downgrade() -> None:
    _fk_table = {
        "umkm_profiles_user_id_foreign": "umkm_profiles",
        "transactions_umkm_id_foreign": "transactions",
        "transactions_outlet_id_foreign": "transactions",
        "transactions_category_id_foreign": "transactions",
        "reports_umkm_id_foreign": "reports",
        "publish_jobs_content_id_foreign": "publish_jobs",
        "products_umkm_id_foreign": "products",
        "market_analyses_umkm_id_foreign": "market_analyses",
        "demographics_umkm_id_foreign": "demographics",
        "demographics_analysis_id_foreign": "demographics",
        "content_assets_umkm_id_foreign": "content_assets",
        "competitors_analysis_id_foreign": "competitors",
    }
    for fk, table in _fk_table.items():
        op.drop_constraint(fk, table, type_="foreignkey")
    _check_table = {
        "users_role_check": "users",
        "transactions_type_check": "transactions",
        "transactions_source_check": "transactions",
        "reports_type_check": "reports",
        "publish_jobs_status_check": "publish_jobs",
        "publish_jobs_platform_check": "publish_jobs",
        "products_status_check": "products",
        "products_category_check": "products",
        "market_analyses_status_check": "market_analyses",
        "demographics_data_source_check": "demographics",
        "content_assets_status_check": "content_assets",
        "content_assets_content_type_check": "content_assets",
        "competitors_sentiment_check": "competitors",
    }
    for check, table in _check_table.items():
        op.drop_constraint(check, table, type_="check")
    for table in (
        "outlets",
        "invites",
        "categories",
        "sessions",
        "password_reset_tokens",
        "migrations",
        "jobs",
        "job_batches",
        "failed_jobs",
        "cache_locks",
        "cache",
        "publish_jobs",
        "reports",
        "transactions",
        "demographics",
        "competitors",
        "market_analyses",
        "content_assets",
        "products",
        "umkm_profiles",
        "users",
    ):
        op.drop_table(table)
