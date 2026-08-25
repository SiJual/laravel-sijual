"""Additive: umkm_memberships (with owner backfill), idempotency_keys, and
the durable Postgres-backed job queue sipromo_jobs (section 17, 16.3, 23).

Revision ID: 0003_memberships_idempotency_jobs
Revises: 0002_knowledge_and_trace
Create Date: 2026-08-17
"""

from __future__ import annotations

import sqlalchemy as sa
from alembic import op
from sqlalchemy.dialects.postgresql import JSONB, UUID

revision: str = "0003_memberships_idempotency"
down_revision: str | None = "0002_knowledge_and_trace"
branch_labels: str | tuple[str, ...] | None = None
depends_on: str | tuple[str, ...] | None = None


def upgrade() -> None:
    op.create_table(
        "umkm_memberships",
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
            sa.ForeignKey("users.id", ondelete="CASCADE"),
            nullable=False,
        ),
        sa.Column("role", sa.String(30), nullable=False),
        sa.Column("status", sa.String(20), nullable=False, server_default=sa.text("'active'")),
        sa.Column(
            "created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()
        ),
        sa.UniqueConstraint("umkm_id", "user_id", name="uq_umkm_memberships_umkm_user"),
        sa.CheckConstraint(
            "role IN ('owner','staff','viewer')", name="umkm_memberships_role_check"
        ),
        sa.CheckConstraint(
            "status IN ('active','suspended')", name="umkm_memberships_status_check"
        ),
    )
    # Idempotent owner backfill from umkm_profiles.user_id.
    op.execute(
        """
        INSERT INTO umkm_memberships (id, umkm_id, user_id, role, status)
        SELECT gen_random_uuid(), p.id, p.user_id, 'owner', 'active'
        FROM umkm_profiles p
        WHERE p.user_id IS NOT NULL
        ON CONFLICT (umkm_id, user_id) DO NOTHING
        """
    )
    op.create_table(
        "idempotency_keys",
        sa.Column("scope", sa.String(255), primary_key=True),
        sa.Column("request_hash", sa.String(64), nullable=False),
        sa.Column("status", sa.String(20), nullable=False, server_default=sa.text("'in_flight'")),
        sa.Column("response_hash", sa.String(64), nullable=True),
        sa.Column(
            "created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()
        ),
        sa.Column("expires_at", sa.DateTime(timezone=True), nullable=False),
        sa.CheckConstraint(
            "status IN ('in_flight','completed')",
            name="idempotency_keys_status_check",
        ),
    )
    op.create_index("idempotency_keys_expires_idx", "idempotency_keys", ["expires_at"])
    op.create_table(
        "sipromo_jobs",
        sa.Column("id", sa.BigInteger(), primary_key=True, autoincrement=True),
        sa.Column("job_type", sa.String(50), nullable=False),
        sa.Column("payload", JSONB, nullable=False),
        sa.Column("status", sa.String(20), nullable=False, server_default=sa.text("'pending'")),
        sa.Column("attempts", sa.Integer(), nullable=False, server_default=sa.text("0")),
        sa.Column(
            "available_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()
        ),
        sa.Column(
            "created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()
        ),
        sa.Column(
            "updated_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()
        ),
        sa.CheckConstraint(
            "status IN ('pending','running','succeeded','failed')",
            name="sipromo_jobs_status_check",
        ),
    )
    op.create_index("sipromo_jobs_claim_idx", "sipromo_jobs", ["status", "available_at"])


def downgrade() -> None:
    op.drop_table("sipromo_jobs")
    op.drop_table("idempotency_keys")
    op.drop_table("umkm_memberships")
