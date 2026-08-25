"""Tenant policy helpers."""

from __future__ import annotations

from uuid import UUID

from sipromo.domain.exceptions import TenantMismatchError


class TenantPolicy:
    @staticmethod
    def assert_owns(tenant_id: UUID, owner_tenant_id: UUID, resource: str = "resource") -> None:
        if tenant_id != owner_tenant_id:
            raise TenantMismatchError(f"{resource} does not belong to the active tenant")
