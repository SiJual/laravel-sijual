"""Shared pytest fixtures.

Conftest sets the app environment to 'test' so the container skips AI
configuration validation. The integration suite manages its own PostgreSQL
(testcontainers) and overrides DATABASE_URL before any engine is created.
"""

from __future__ import annotations

import asyncio
import os
import uuid
from collections.abc import Iterator

import pytest
import pytest_asyncio

os.environ.setdefault("APP_ENV", "test")
os.environ.setdefault("JWT_SECRET", "test-secret-key")
os.environ.setdefault("OPENAI_API_KEY", "test-key")

pytest_plugins = ["pytest_asyncio"]


def _clean_env() -> None:
    """Isolate tests from a developer's real .env (no network in unit tests)."""
    for key in (
        "DATABASE_URL",
        "CLOUDINARY_CLOUD_NAME",
        "CLOUDINARY_API_KEY",
        "CLOUDINARY_API_SECRET",
        "CLOUDINARY_FOLDER",
    ):
        os.environ.pop(key, None)


_clean_env()


@pytest.fixture(scope="session")
def event_loop() -> Iterator[asyncio.AbstractEventLoop]:
    loop = asyncio.new_event_loop()
    yield loop
    loop.close()


@pytest_asyncio.fixture
async def app_container():
    from sipromo.bootstrap.container import Container
    from sipromo.bootstrap.settings import get_settings

    get_settings.cache_clear()
    container = Container(get_settings())
    container.build_use_cases()
    yield container
    await container.session_factory.close()


@pytest.fixture
def actor() -> dict:
    return {
        "user_id": uuid.uuid4(),
        "umkm_id": uuid.uuid4(),
        "role": "staff",
    }


@pytest.fixture
def jwt_service(app_container):
    return app_container.jwt_service
