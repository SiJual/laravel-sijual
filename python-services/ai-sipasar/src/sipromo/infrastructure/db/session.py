"""Async database session management with contextvar-scoped sessions and a
UnitOfWork that sets tenant context (RLS defense-in-depth) per transaction.
"""

from __future__ import annotations

import logging
from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from contextvars import ContextVar
from uuid import UUID

from sqlalchemy import text
from sqlalchemy.ext.asyncio import (
    AsyncEngine,
    AsyncSession,
    async_sessionmaker,
    create_async_engine,
)

from sipromo.application.ports.transaction_manager import UnitOfWorkPort
from sipromo.bootstrap.settings import Settings

logger = logging.getLogger(__name__)

_session_var: ContextVar[AsyncSession | None] = ContextVar("sipromo_session", default=None)
_tenant_var: ContextVar[UUID | None] = ContextVar("sipromo_tenant", default=None)
_user_var: ContextVar[UUID | None] = ContextVar("sipromo_user", default=None)


def get_current_session() -> AsyncSession:
    session = _session_var.get()
    if session is None:
        raise RuntimeError("No active database session; begin a unit of work first")
    return session


class SessionFactory:
    """Owns the async engine and sessionmaker; disposes on app shutdown."""

    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._engine: AsyncEngine | None = None
        self._maker: async_sessionmaker[AsyncSession] | None = None

    def _ensure(self) -> async_sessionmaker[AsyncSession]:
        if self._maker is None:
            self._engine = create_async_engine(
                self._settings.database_url,
                pool_size=self._settings.database_pool_size,
                max_overflow=self._settings.database_max_overflow,
                pool_pre_ping=True,
                echo=False,
            )
            self._maker = async_sessionmaker(
                self._engine, class_=AsyncSession, expire_on_commit=False
            )
        return self._maker

    def maker(self) -> async_sessionmaker[AsyncSession]:
        return self._ensure()

    @asynccontextmanager
    async def session(self) -> AsyncIterator[AsyncSession]:
        """Short-lived session with no tenant context (low-level access)."""
        session = self._ensure()()
        try:
            yield session
            await session.commit()
        except Exception:
            await session.rollback()
            raise
        finally:
            await session.close()

    async def dispose(self) -> None:
        if self._engine is not None:
            await self._engine.dispose()
            self._engine = None
            self._maker = None


class SqlAlchemyUnitOfWork(UnitOfWorkPort):
    """Transaction scope. Repositories obtain the session via get_current_session().

    begin() is composable: nested begin() calls inside an active transaction
    share the same session and do not commit prematurely. Use as an async
    context manager; the transaction commits on clean exit and rolls back on
    exception.
    """

    def __init__(self, session_factory: SessionFactory) -> None:
        self._session_factory = session_factory
        self._active_depth = 0
        self._session: AsyncSession | None = None

    @asynccontextmanager
    async def begin(self) -> AsyncIterator[None]:
        self._active_depth += 1
        created = self._active_depth == 1
        if created:
            session = self._session_factory.maker()()
            _session_var.set(session)
            await session.begin()
            tenant = _tenant_var.get()
            user = _user_var.get()
            if tenant is not None:
                await session.execute(
                    text("SELECT set_config('app.current_umkm_id', :umkm_id, true)"),
                    {"umkm_id": str(tenant)},
                )
            if user is not None:
                await session.execute(
                    text("SELECT set_config('app.current_user_id', :user_id, true)"),
                    {"user_id": str(user)},
                )
            self._session = session
        error: BaseException | None = None
        try:
            yield
        except BaseException as exc:
            error = exc
            raise
        finally:
            self._active_depth -= 1
            if self._active_depth == 0 and self._session is not None:
                session = self._session
                self._session = None
                _session_var.set(None)
                try:
                    if error is None:
                        await session.commit()
                    else:
                        await session.rollback()
                except Exception:
                    await session.rollback()
                    raise
                finally:
                    await session.close()

    async def commit(self) -> None:
        if self._active_depth == 1 and self._session is not None:
            await self._session.commit()

    async def rollback(self) -> None:
        if self._active_depth == 1 and self._session is not None:
            await self._session.rollback()

    async def ping(self) -> bool:
        """Lightweight database reachability check (no tenant context needed)."""
        session = self._session_factory.maker()()
        try:
            await session.execute(text("SELECT 1"))
            return True
        except Exception:
            return False
        finally:
            await session.close()

    def set_tenant(self, umkm_id: object) -> None:
        _tenant_var.set(umkm_id)  # type: ignore[arg-type]

    def set_user(self, user_id: object | None) -> None:
        _user_var.set(user_id)  # type: ignore[arg-type]

    @asynccontextmanager
    async def session(self) -> AsyncIterator[AsyncSession]:
        """The active transaction's session (call inside begin())."""
        session = _session_var.get()
        if session is None:
            raise RuntimeError("no active transaction; call begin() first")
        yield session


class TransactionManager:
    """A single async context manager giving access to the unit of work."""

    def __init__(self, unit_of_work: UnitOfWorkPort) -> None:
        self._unit_of_work = unit_of_work

    @asynccontextmanager
    async def transaction(
        self, *, tenant: UUID | None = None, user: UUID | None = None
    ) -> AsyncIterator[None]:
        if tenant is not None:
            self._unit_of_work.set_tenant(tenant)
        if user is not None:
            self._unit_of_work.set_user(user)
        async with self._unit_of_work.begin():
            yield

    @property
    def unit_of_work(self) -> UnitOfWorkPort:
        return self._unit_of_work
