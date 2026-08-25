"""Transaction / unit-of-work port."""

from __future__ import annotations

from contextlib import AbstractAsyncContextManager
from typing import Protocol


class UnitOfWorkPort(Protocol):
    def begin(self) -> AbstractAsyncContextManager[None]:
        """Open a transaction (and tenant context). Commits on clean exit,
        rolls back on error. Composable: nested begin() calls share the
        active transaction."""
        ...

    async def commit(self) -> None: ...

    async def rollback(self) -> None: ...

    def set_tenant(self, umkm_id: object) -> None: ...

    def set_user(self, user_id: object | None) -> None: ...
