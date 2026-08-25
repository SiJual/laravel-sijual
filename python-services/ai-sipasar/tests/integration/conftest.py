"""Integration fixtures: ephemeral PostgreSQL + pgvector via testcontainers.

The suite runs migrations head-first against a throwaway container per
session, then hands every test a bound SessionFactory. Tests that need the
vector index to fire use real embeddings-style vectors (dimension 768).

Marks: integration (see pyproject markers). Skipped when Docker is absent.
"""

from __future__ import annotations

import asyncio
import os
from collections.abc import AsyncIterator
from pathlib import Path

import pytest
import pytest_asyncio

pytestmark = pytest.mark.integration

_ROOT = Path(__file__).resolve().parents[2]


@pytest_asyncio.fixture(scope="session")
async def postgres_url() -> AsyncIterator[str]:
    from testcontainers.community.postgres import PostgresContainer

    with PostgresContainer("pgvector/pgvector:pg16") as pg:
        url = pg.get_connection_url().replace("+psycopg2", "+asyncpg")
        yield url


@pytest_asyncio.fixture(scope="session")
async def migrated_url(postgres_url: str) -> AsyncIterator[str]:
    """Run alembic upgrade head inside the container (once per session)."""
    from alembic import command
    from alembic.config import Config

    os.environ["DATABASE_URL"] = postgres_url
    from sipromo.bootstrap.settings import get_settings

    get_settings.cache_clear()
    cfg = Config(str(_ROOT / "alembic.ini"))
    cfg.set_main_option("script_location", str(_ROOT / "migrations"))
    cfg.set_main_option("sqlalchemy.url", postgres_url)
    await asyncio.to_thread(command.upgrade, cfg, "head")
    yield postgres_url
    os.environ.pop("DATABASE_URL", None)
    get_settings.cache_clear()


@pytest_asyncio.fixture
async def session_factory(migrated_url: str):
    from sipromo.bootstrap.settings import get_settings
    from sipromo.infrastructure.db.session import SessionFactory

    original = os.environ.get("DATABASE_URL")
    os.environ["DATABASE_URL"] = migrated_url
    get_settings.cache_clear()
    factory = SessionFactory(get_settings())
    yield factory
    await factory.dispose()
    if original is None:
        os.environ.pop("DATABASE_URL", None)
    else:
        os.environ["DATABASE_URL"] = original
    get_settings.cache_clear()


@pytest_asyncio.fixture
async def clean_db(session_factory) -> AsyncIterator[None]:
    """Truncate all tables between tests (fast, avoids re-migrating)."""
    from sqlalchemy import text

    async with session_factory.session() as session:
        await session.execute(text("SET session_replication_role = replica"))
        await session.execute(
            text(
                """
                SELECT pg_catalog.set_config(
                'app.current_umkm_id',
                '00000000-0000-0000-0000-000000000000',
                false
            )
                """
            )
        )
        await session.execute(
            text(
                """
                SELECT table_name FROM information_schema.tables
                WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
                """
            ).execution_options(no_autoflush=True)
        )
        await session.execute(
            text(
                """
                DO $$
                DECLARE r RECORD;
                BEGIN
                    FOR r IN SELECT tablename FROM pg_tables WHERE schemaname = 'public'
                    LOOP
                        EXECUTE 'TRUNCATE TABLE public.' || quote_ident(r.tablename) || ' CASCADE';
                    END LOOP;
                END $$;
                """
            )
        )
        await session.execute(text("SET session_replication_role = origin"))
        await session.commit()
    yield
