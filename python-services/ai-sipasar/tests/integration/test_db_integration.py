"""Integration tests: migrations, UoW semantics, tenant isolation, RLS,
vector + FTS retrieval, knowledge lifecycle, idempotency, and memberships.

Each test runs against the migrated testcontainers PostgreSQL (pgvector).
"""

from __future__ import annotations

import uuid
from pathlib import Path

import pytest
from sqlalchemy import text

pytestmark = pytest.mark.integration

# helpers -----------------------------------------------------------------

EMBEDDING_DIM = 768


def make_embedding(seed: float) -> list[float]:
    """Deterministic pseudo-vector: hot term at index 0, otherwise near-zero."""
    vec = [seed] + [0.0] * (EMBEDDING_DIM - 1)
    return vec


def _uuid(prefix: int = 1) -> uuid.UUID:
    return uuid.UUID(f"00000000-0000-0000-0000-{prefix:012d}")


async def seed_tenant(
    session_factory,
    umkm_id: uuid.UUID,
    user_id: uuid.UUID,
    product_name: str = "Keripik Pedas",
) -> uuid.UUID:
    """Insert user, profile, one product, and an owner membership; returns product id."""
    async with session_factory.session() as session:
        stmt = text(
            """
            INSERT INTO users (id, full_name, email, password, role)
            VALUES (:uid, 'Owner', :email, 'x', 'owner')
            ON CONFLICT (id) DO NOTHING
            """
        )
        await session.execute(stmt, {"uid": user_id, "email": f"{user_id}@x.id"})
        stmt = text(
            """
            INSERT INTO umkm_profiles (id, user_id, business_name, business_type, city)
            VALUES (:uid, :owner, :name, 'food', 'Jakarta')
            ON CONFLICT (id) DO NOTHING
            """
        )
        await session.execute(
            stmt, {"uid": umkm_id, "owner": user_id, "name": "UMKM " + str(umkm_id)}
        )
        stmt = text(
            """
            INSERT INTO products (id, umkm_id, name, category, price, stock_level)
            VALUES (:uid, :umkm, :name, 'food_bev', 25000, 100)
            ON CONFLICT (id) DO NOTHING
            """
        )
        product_id = uuid.uuid4()
        await session.execute(stmt, {"uid": product_id, "umkm": umkm_id, "name": product_name})
        await session.execute(
            text(
                """
                INSERT INTO umkm_memberships (id, umkm_id, user_id, role, status)
                VALUES (:uid, :umkm, :user, 'owner', 'active')
                ON CONFLICT (umkm_id, user_id) DO NOTHING
                """
            ),
            {"uid": uuid.uuid4(), "umkm": umkm_id, "user": user_id},
        )
        await session.commit()
    return product_id


# migrations --------------------------------------------------------------


async def test_migrations_reach_head(migrated_url: str) -> None:
    from sqlalchemy import create_engine

    sync_url = migrated_url.replace("+asyncpg", "+psycopg2")
    engine = create_engine(sync_url)
    with engine.connect() as conn:
        version = conn.execute(text("SELECT version_num FROM alembic_version")).scalar_one()
        assert version == "0003_memberships_idempotency"
        ext = conn.execute(
            text("SELECT extname FROM pg_extension WHERE extname = 'vector'")
        ).scalar_one_or_none()
        assert ext == "vector"
        index = conn.execute(
            text(
                "SELECT indexname FROM pg_indexes "
                "WHERE indexname = 'knowledge_chunks_embedding_hnsw_idx'"
            )
        ).scalar_one_or_none()
        assert index is not None
    engine.dispose()


async def test_downgrade_upgrade_roundtrip(migrated_url: str) -> None:
    """Alembic env runs its own event loop - drive it from a subprocess."""
    import asyncio
    import os
    import sys

    root = str(Path(__file__).resolve().parents[2])

    async def run_alembic(direction: str, revision: str) -> None:
        env = dict(os.environ)
        env["DATABASE_URL"] = migrated_url
        result = await asyncio.create_subprocess_exec(
            sys.executable,
            "-m",
            "alembic",
            direction,
            revision,
            cwd=root,
            env=env,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        _, stderr = await result.communicate()
        assert result.returncode == 0, stderr.decode()[-800:]

    for direction, revision in (("downgrade", "0002_knowledge_and_trace"), ("upgrade", "head")):
        await run_alembic(direction, revision)
    assert True


# UoW semantics ------------------------------------------------------------


async def test_unit_of_work_commits_and_rolls_back(session_factory) -> None:
    from sqlalchemy import text

    from sipromo.infrastructure.db.session import SqlAlchemyUnitOfWork

    uow = SqlAlchemyUnitOfWork(session_factory)
    umkm = _uuid(11)
    user = _uuid(110)
    user2 = _uuid(120)
    async with session_factory.session() as s:
        await s.execute(
            text(
                "INSERT INTO users (id, email, password, role) "
                "VALUES (:u, :e, 'x', 'owner') ON CONFLICT (id) DO NOTHING"
            ),
            {"u": user, "e": f"{user}@x.id"},
        )
        await s.execute(
            text(
                "INSERT INTO users (id, email, password, role) "
                "VALUES (:u, :e, 'x', 'owner') ON CONFLICT (id) DO NOTHING"
            ),
            {"u": user2, "e": f"{user2}@x.id"},
        )
        await s.commit()
    async with uow.begin(), uow.session() as s:
        await s.execute(
            text("INSERT INTO umkm_profiles (id, user_id, business_name) VALUES (:i, :u, 'T1')"),
            {"i": umkm, "u": user},
        )
    # committed: visible in a fresh session
    async with session_factory.session() as s:
        row = await s.execute(
            text("SELECT business_name FROM umkm_profiles WHERE id = :i"), {"i": umkm}
        )
        assert row.scalar_one() == "T1"

    with pytest.raises(RuntimeError):
        async with uow.begin():
            async with uow.session() as s:
                await s.execute(
                    text(
                        "INSERT INTO umkm_profiles (id, user_id, business_name)"
                        " VALUES (:i, :u, 'T2')"
                    ),
                    {"i": _uuid(12), "u": user2},
                )
                raise RuntimeError("boom")
    async with session_factory.session() as s:
        row = await s.execute(text("SELECT 1 FROM umkm_profiles WHERE id = :i"), {"i": _uuid(12)})
        assert row.scalar_one_or_none() is None


async def test_unit_of_work_sets_tenant_context(session_factory) -> None:
    from sqlalchemy import text

    from sipromo.infrastructure.db.session import SqlAlchemyUnitOfWork

    uow = SqlAlchemyUnitOfWork(session_factory)
    umkm = _uuid(13)
    uow.set_tenant(umkm)
    async with uow.begin(), uow.session() as s:
        val = await s.execute(text("SELECT current_setting('app.current_umkm_id', true)"))
        assert val.scalar_one() == str(umkm)


# RLS ----------------------------------------------------------------------


async def test_rls_blocks_foreign_tenant_for_unprivileged_role(
    migrated_url: str, session_factory
) -> None:
    """A non-owner role without the session variable sees no rows (FORCE RLS)."""
    from sqlalchemy import create_engine
    from sqlalchemy import text as sql_text
    from sqlalchemy.orm import sessionmaker

    umkm_a = _uuid(21)
    umkm_b = _uuid(22)
    await seed_tenant(session_factory, umkm_a, _uuid(23))
    await seed_tenant(session_factory, umkm_b, _uuid(24))
    doc_ids: dict = {}
    async with session_factory.session() as s:
        for umkm in (umkm_a, umkm_b):
            doc_id = uuid.uuid4()
            doc_ids[umkm] = doc_id
            await s.execute(
                text(
                    """
                    INSERT INTO knowledge_documents
                        (id, umkm_id, title, document_type, source_type,
                         checksum_sha256, status)
                    VALUES (:id, :umkm, 'Doc', 'faq', 'manual', :chk, 'ready')
                    """
                ),
                {"id": doc_id, "umkm": umkm, "chk": uuid.uuid4().hex},
            )

    sync_url = migrated_url.replace("+asyncpg", "+psycopg2")
    engine = create_engine(sync_url)
    with engine.connect() as conn:
        conn.execute(sql_text("DROP ROLE IF EXISTS app_role"))
        conn.execute(sql_text("CREATE ROLE app_role LOGIN PASSWORD 'app' NOSUPERUSER NOBYPASSRLS"))
        conn.execute(
            sql_text(
                "GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_role"
            )
        )
        conn.execute(sql_text("GRANT USAGE ON SCHEMA public TO app_role"))
        conn.commit()

    from urllib.parse import urlparse

    parsed = urlparse(sync_url)
    role_url = f"postgresql+psycopg2://app_role:app@{parsed.hostname}:{parsed.port}{parsed.path}"
    role_engine = create_engine(role_url)
    limited = sessionmaker(bind=role_engine)
    with limited() as s:
        s.execute(
            sql_text("SET app.current_umkm_id = :v"),
            {"v": str(umkm_a)},
        )
        rows = s.execute(sql_text("SELECT id FROM knowledge_documents")).fetchall()
        assert {r[0] for r in rows} == {doc_ids[umkm_a]}
        # no session var set -> FORCE RLS hides everything
        with limited() as s2:
            rows2 = s2.execute(sql_text("SELECT id FROM knowledge_documents")).fetchall()
            assert rows2 == []
    role_engine.dispose()
    engine.dispose()


# knowledge lifecycle ------------------------------------------------------


async def test_ingest_document_then_vector_and_lexical_search(session_factory, clean_db) -> None:
    from sipromo.domain.entities.knowledge_document import (
        KnowledgeChunk,
        KnowledgeDocument,
    )
    from sipromo.domain.value_objects.content_type import DocumentStatus, DocumentType
    from sipromo.infrastructure.db.repositories.knowledge_repository import (
        KnowledgeRepository,
    )
    from sipromo.infrastructure.db.session import SqlAlchemyUnitOfWork

    umkm = _uuid(31)
    user = _uuid(32)
    await seed_tenant(session_factory, umkm, user)

    uow = SqlAlchemyUnitOfWork(session_factory)
    repo = KnowledgeRepository()
    doc = KnowledgeDocument(
        document_id=uuid.uuid4(),
        umkm_id=umkm,
        title="Katalog Keripik 2026",
        document_type=DocumentType.PRODUCT_CATALOG,
        checksum_sha256="a" * 64,
        status=DocumentStatus.PROCESSING,
    )
    chunk = KnowledgeChunk(
        chunk_id=uuid.uuid4(),
        document_id=doc.document_id,
        umkm_id=umkm,
        chunk_index=0,
        content="Keripik pedas dijual dengan harga Rp25.000 per bungkus.",
        token_count=12,
        embedding=make_embedding(0.9),
        metadata={},
    )
    async with uow.begin():
        await repo.create_document(doc)
        await repo.insert_chunks([chunk])
        await repo.update_document_status(umkm, doc.document_id, DocumentStatus.READY)
    async with uow.begin():
        found = await repo.get_document(umkm, doc.document_id)
        assert found is not None and found.status == "ready"
        hits = await repo.vector_search(umkm, query_embedding=make_embedding(0.95), limit=5)
        assert hits and hits[0].document_id == doc.document_id
        lex = await repo.lexical_search(umkm, query="keripik harga", limit=5)
        assert any(c.document_id == doc.document_id for c in lex)
        archived = await repo.get_document(umkm, doc.document_id)
        assert archived is not None
        await repo.archive_document(umkm, doc.document_id)
    async with uow.begin():
        assert (await repo.get_document(umkm, doc.document_id)).status == "archived"
        await repo.hard_delete_document(umkm, doc.document_id)
        assert await repo.get_document(umkm, doc.document_id) is None


async def test_vector_search_is_tenant_scoped(session_factory, clean_db) -> None:
    from sipromo.domain.entities.knowledge_document import (
        KnowledgeChunk,
        KnowledgeDocument,
    )
    from sipromo.domain.value_objects.content_type import DocumentStatus, DocumentType
    from sipromo.infrastructure.db.repositories.knowledge_repository import (
        KnowledgeRepository,
    )
    from sipromo.infrastructure.db.session import SqlAlchemyUnitOfWork

    umkm_a, umkm_b = _uuid(41), _uuid(42)
    await seed_tenant(session_factory, umkm_a, _uuid(43))
    await seed_tenant(session_factory, umkm_b, _uuid(44))
    repo = KnowledgeRepository()
    uow = SqlAlchemyUnitOfWork(session_factory)

    docs = []
    for umkm in (umkm_a, umkm_b):
        d = KnowledgeDocument(
            document_id=uuid.uuid4(),
            umkm_id=umkm,
            title="Doc",
            document_type=DocumentType.FAQ,
            checksum_sha256=uuid.uuid4().hex,
            status=DocumentStatus.READY,
        )
        docs.append(d)
        c = KnowledgeChunk(
            chunk_id=uuid.uuid4(),
            document_id=d.document_id,
            umkm_id=umkm,
            chunk_index=0,
            content="Konten unik untuk tenant ini.",
            token_count=8,
            embedding=make_embedding(0.8),
            metadata={},
        )
        async with uow.begin():
            await repo.create_document(d)
            await repo.insert_chunks([c])
    async with uow.begin():
        hits_a = await repo.vector_search(umkm_a, query_embedding=make_embedding(0.85), limit=10)
        hits_b = await repo.vector_search(umkm_b, query_embedding=make_embedding(0.85), limit=10)
        assert {h.document_id for h in hits_a} == {docs[0].document_id}
        assert {h.document_id for h in hits_b} == {docs[1].document_id}


# idempotency + memberships -------------------------------------------------


async def test_idempotency_repository(session_factory, clean_db) -> None:
    from sipromo.infrastructure.db.repositories.misc_repositories import (
        IdempotencyRepositoryImpl,
    )
    from sipromo.infrastructure.db.session import SqlAlchemyUnitOfWork

    repo = IdempotencyRepositoryImpl()
    scope = "generate:00000000-0000-0000-0000-000000000099"
    uow = SqlAlchemyUnitOfWork(session_factory)
    async with uow.begin():
        status, response_hash = await repo.get_or_create(
            scope=scope, request_hash="hash1", ttl_seconds=3600
        )
        assert status == "in_flight" and response_hash is None
        status2, _ = await repo.get_or_create(scope=scope, request_hash="hash1", ttl_seconds=3600)
        assert status2 == "in_flight"
        await repo.complete(scope, "response-hash")
    async with uow.begin():
        status3, response_hash3 = await repo.get_or_create(
            scope=scope, request_hash="hash1", ttl_seconds=3600
        )
        assert status3 == "completed" and response_hash3 == "response-hash"


async def test_membership_repository(session_factory, clean_db) -> None:
    from sipromo.infrastructure.db.repositories.misc_repositories import (
        MembershipRepositoryImpl,
    )
    from sipromo.infrastructure.db.session import SqlAlchemyUnitOfWork

    umkm, owner, stranger = _uuid(51), _uuid(52), _uuid(53)
    await seed_tenant(session_factory, umkm, owner)
    repo = MembershipRepositoryImpl()
    uow = SqlAlchemyUnitOfWork(session_factory)
    async with uow.begin():
        assert await repo.get_membership(umkm, owner) == "owner"
        assert await repo.get_membership(umkm, stranger) is None


# business reads --------------------------------------------------------------


async def test_business_repository_facts(session_factory, clean_db) -> None:
    from sipromo.infrastructure.db.repositories.business_repository import (
        BusinessRepository,
    )
    from sipromo.infrastructure.db.session import SqlAlchemyUnitOfWork

    umkm = _uuid(61)
    product_id = await seed_tenant(session_factory, umkm, _uuid(62))
    repo = BusinessRepository()
    uow = SqlAlchemyUnitOfWork(session_factory)
    async with uow.begin():
        profile = await repo.get_business_profile(umkm)
        assert profile is not None and profile.city == "Jakarta"
        assert await repo.get_products(umkm, []) == []
        products = await repo.get_products(umkm, [product_id])
        assert products[0].name == "Keripik Pedas"
        eligibility = await repo.get_inventory_eligibility(umkm, [product_id])
        assert len(eligibility) == 1 and eligibility[0].eligible is True
        assert await repo.get_latest_market_summary(umkm) is None
