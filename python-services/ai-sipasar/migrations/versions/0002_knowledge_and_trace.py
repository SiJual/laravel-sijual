"""Additive: pgvector knowledge tables + generation trace tables
(knowledge_documents, knowledge_chunks, generation_runs,
generation_tool_calls, content_sources, content_revisions,
content_approvals) per blueprint section 7.3, with RLS.

The embedding dimension is read from EMBEDDING_DIM (default 768) and is
locked by this migration - changing the provider dimension requires a new
migration, never an in-place edit.

Revision ID: 0002_knowledge_and_trace
Revises: 0001_legacy_baseline
Create Date: 2026-08-17
"""

from __future__ import annotations

import os

import sqlalchemy as sa
from alembic import op
from sqlalchemy.dialects.postgresql import JSONB, UUID

revision: str = "0002_knowledge_and_trace"
down_revision: str | None = "0001_legacy_baseline"
branch_labels: str | tuple[str, ...] | None = None
depends_on: str | tuple[str, ...] | None = None

EMBEDDING_DIM = int(os.environ.get("EMBEDDING_DIM", "768"))


def upgrade() -> None:
    op.execute("CREATE EXTENSION IF NOT EXISTS vector")

    op.create_table(
        "knowledge_documents",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column(
            "umkm_id",
            UUID(as_uuid=True),
            sa.ForeignKey("umkm_profiles.id", ondelete="CASCADE"),
            nullable=False,
        ),
        sa.Column("title", sa.String(255), nullable=False),
        sa.Column("document_type", sa.String(50), nullable=False),
        sa.Column("source_type", sa.String(30), nullable=False),
        sa.Column("cloudinary_public_id", sa.String(255), nullable=True),
        sa.Column("source_url", sa.Text(), nullable=True),
        sa.Column("mime_type", sa.String(100), nullable=True),
        sa.Column("checksum_sha256", sa.String(64), nullable=False),
        sa.Column("status", sa.String(30), nullable=False, server_default=sa.text("'pending'")),
        sa.Column("metadata", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column(
            "created_by",
            UUID(as_uuid=True),
            sa.ForeignKey("users.id", ondelete="SET NULL"),
            nullable=True,
        ),
        sa.Column(
            "created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()
        ),
        sa.Column(
            "updated_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()
        ),
        sa.CheckConstraint(
            "status IN ('pending','processing','ready','failed','archived')",
            name="knowledge_documents_status_check",
        ),
        sa.CheckConstraint(
            "document_type IN "
            "('brand_guide','product_catalog','faq','campaign_example','policy','other')",
            name="knowledge_documents_type_check",
        ),
        sa.CheckConstraint(
            "source_type IN ('upload','manual','database_snapshot')",
            name="knowledge_documents_source_check",
        ),
        sa.UniqueConstraint(
            "umkm_id", "checksum_sha256", name="uq_knowledge_documents_umkm_checksum"
        ),
    )
    op.create_table(
        "knowledge_chunks",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column(
            "document_id",
            UUID(as_uuid=True),
            sa.ForeignKey("knowledge_documents.id", ondelete="CASCADE"),
            nullable=False,
        ),
        sa.Column(
            "umkm_id",
            UUID(as_uuid=True),
            sa.ForeignKey("umkm_profiles.id", ondelete="CASCADE"),
            nullable=False,
        ),
        sa.Column("chunk_index", sa.Integer(), nullable=False),
        sa.Column("content", sa.Text(), nullable=False),
        sa.Column("token_count", sa.Integer(), nullable=False),
        sa.Column("embedding", sa.dialects.postgresql.ARRAY(sa.Float()), nullable=False),
        sa.Column("metadata", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column(
            "created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()
        ),
        sa.UniqueConstraint("document_id", "chunk_index", name="uq_knowledge_chunks_doc_index"),
    )
    # Convert to pgvector with the locked dimension.
    op.execute(
        f"ALTER TABLE knowledge_chunks "
        f"ALTER COLUMN embedding TYPE vector({EMBEDDING_DIM}) USING embedding::vector"
    )
    op.create_index("knowledge_chunks_tenant_idx", "knowledge_chunks", ["umkm_id", "document_id"])
    op.execute(
        "CREATE INDEX knowledge_chunks_embedding_hnsw_idx "
        "ON knowledge_chunks USING hnsw (embedding vector_cosine_ops)"
    )
    op.execute(
        "CREATE INDEX knowledge_chunks_content_fts_idx "
        "ON knowledge_chunks USING gin (to_tsvector('simple', content))"
    )

    op.create_table(
        "generation_runs",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column(
            "umkm_id",
            UUID(as_uuid=True),
            sa.ForeignKey("umkm_profiles.id", ondelete="CASCADE"),
            nullable=False,
        ),
        sa.Column(
            "user_id",
            UUID(as_uuid=True),
            sa.ForeignKey("users.id", ondelete="SET NULL"),
            nullable=True,
        ),
        sa.Column(
            "content_asset_id",
            UUID(as_uuid=True),
            sa.ForeignKey("content_assets.id", ondelete="SET NULL"),
            nullable=True,
        ),
        sa.Column("request_id", UUID(as_uuid=True), nullable=False),
        sa.Column("model_provider", sa.String(50), nullable=False),
        sa.Column("model_name", sa.String(100), nullable=False),
        sa.Column("prompt_version", sa.String(50), nullable=False),
        sa.Column("status", sa.String(30), nullable=False),
        sa.Column("brief", JSONB, nullable=False),
        sa.Column(
            "retrieved_context", JSONB, nullable=False, server_default=sa.text("'[]'::jsonb")
        ),
        sa.Column("usage_metadata", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column(
            "validation_metadata", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")
        ),
        sa.Column("error_code", sa.String(100), nullable=True),
        sa.Column(
            "created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()
        ),
        sa.Column("completed_at", sa.DateTime(timezone=True), nullable=True),
        sa.CheckConstraint(
            "status IN ('started','completed','failed','rejected')",
            name="generation_runs_status_check",
        ),
    )
    op.execute(
        "CREATE INDEX generation_runs_tenant_created_idx "
        "ON generation_runs (umkm_id, created_at DESC)"
    )
    op.create_table(
        "generation_tool_calls",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column(
            "generation_run_id",
            UUID(as_uuid=True),
            sa.ForeignKey("generation_runs.id", ondelete="CASCADE"),
            nullable=False,
        ),
        sa.Column("tool_name", sa.String(100), nullable=False),
        sa.Column("arguments", JSONB, nullable=False),
        sa.Column("result_summary", JSONB, nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column("status", sa.String(30), nullable=False),
        sa.Column("duration_ms", sa.Integer(), nullable=True),
        sa.Column(
            "created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()
        ),
        sa.CheckConstraint(
            "status IN ('requested','succeeded','failed','denied')",
            name="generation_tool_calls_status_check",
        ),
    )
    op.create_table(
        "content_sources",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column(
            "content_asset_id",
            UUID(as_uuid=True),
            sa.ForeignKey("content_assets.id", ondelete="CASCADE"),
            nullable=False,
        ),
        sa.Column("source_kind", sa.String(30), nullable=False),
        sa.Column("source_ref", sa.String(255), nullable=False),
        sa.Column(
            "chunk_id",
            UUID(as_uuid=True),
            sa.ForeignKey("knowledge_chunks.id", ondelete="SET NULL"),
            nullable=True,
        ),
        sa.Column("claim_keys", JSONB, nullable=False, server_default=sa.text("'[]'::jsonb")),
        sa.Column("relevance_score", sa.Float(), nullable=True),
        sa.Column("excerpt", sa.Text(), nullable=True),
        sa.Column(
            "created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()
        ),
        sa.CheckConstraint(
            "source_kind IN ('rag_chunk','tool_result','user_input','system_rule')",
            name="content_sources_kind_check",
        ),
    )
    op.create_table(
        "content_revisions",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column(
            "content_asset_id",
            UUID(as_uuid=True),
            sa.ForeignKey("content_assets.id", ondelete="CASCADE"),
            nullable=False,
        ),
        sa.Column("version", sa.Integer(), nullable=False),
        sa.Column(
            "parent_revision_id",
            UUID(as_uuid=True),
            sa.ForeignKey("content_revisions.id", ondelete="SET NULL"),
            nullable=True,
        ),
        sa.Column(
            "changed_by",
            UUID(as_uuid=True),
            sa.ForeignKey("users.id", ondelete="SET NULL"),
            nullable=True,
        ),
        sa.Column("change_reason", sa.Text(), nullable=True),
        sa.Column("payload", JSONB, nullable=False),
        sa.Column(
            "created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()
        ),
        sa.UniqueConstraint(
            "content_asset_id", "version", name="uq_content_revisions_asset_version"
        ),
    )
    op.create_table(
        "content_approvals",
        sa.Column("id", UUID(as_uuid=True), primary_key=True),
        sa.Column(
            "content_asset_id",
            UUID(as_uuid=True),
            sa.ForeignKey("content_assets.id", ondelete="CASCADE"),
            nullable=False,
        ),
        sa.Column(
            "revision_id",
            UUID(as_uuid=True),
            sa.ForeignKey("content_revisions.id", ondelete="SET NULL"),
            nullable=True,
        ),
        sa.Column(
            "decided_by",
            UUID(as_uuid=True),
            sa.ForeignKey("users.id", ondelete="RESTRICT"),
            nullable=False,
        ),
        sa.Column("decision", sa.String(20), nullable=False),
        sa.Column("notes", sa.Text(), nullable=True),
        sa.Column(
            "created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()
        ),
        sa.CheckConstraint(
            "decision IN ('approved','rejected','changes_requested')",
            name="content_approvals_decision_check",
        ),
    )

    # RLS is enabled on tables that carry umkm_id directly (defense in depth).
    # Child tables (tool calls, sources, revisions, approvals) are reached only
    # through parent FKs and repository tenant filters.
    _enable_rls("knowledge_documents", "knowledge_chunks", "generation_runs")


def _enable_rls(*tables: str) -> None:
    """RLS defense-in-depth: tenant scoped via app.current_umkm_id session var.

    The application sets the variable at transaction start (SET LOCAL). When
    the app role is not the table owner, RLS enforces the tenant boundary
    even if a repository filter were ever missed.
    """
    for table in tables:
        op.execute(f"ALTER TABLE {table} ENABLE ROW LEVEL SECURITY")
        op.execute(f"ALTER TABLE {table} FORCE ROW LEVEL SECURITY")
        op.execute(
            f"""
            CREATE POLICY {table}_tenant_policy
            ON {table}
            USING (umkm_id = current_setting('app.current_umkm_id', true)::uuid)
            WITH CHECK (umkm_id = current_setting('app.current_umkm_id', true)::uuid)
            """
        )


def downgrade() -> None:
    for table in (
        "content_approvals",
        "content_revisions",
        "content_sources",
        "generation_tool_calls",
        "generation_runs",
        "knowledge_chunks",
        "knowledge_documents",
    ):
        op.execute(f"DROP POLICY IF EXISTS {table}_tenant_policy ON {table}")
    op.drop_table("content_approvals")
    op.drop_table("content_revisions")
    op.drop_table("content_sources")
    op.drop_table("generation_tool_calls")
    op.drop_table("generation_runs")
    op.drop_table("knowledge_chunks")
    op.drop_table("knowledge_documents")
    op.execute("DROP EXTENSION IF EXISTS vector")
