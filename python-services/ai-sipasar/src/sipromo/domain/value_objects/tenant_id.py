from uuid import UUID


class TenantId:
    """Value object for tenant identity. Wraps a UUID with invariant checks."""

    __slots__ = ("value",)

    def __init__(self, value: UUID | str) -> None:
        if isinstance(value, str):
            value = UUID(value)
        if not isinstance(value, UUID):
            raise ValueError("TenantId must be a UUID")
        self.value = value

    def __eq__(self, other: object) -> bool:
        return isinstance(other, TenantId) and self.value == other.value

    def __hash__(self) -> int:
        return hash(self.value)

    def __str__(self) -> str:
        return str(self.value)

    def __repr__(self) -> str:
        return f"TenantId({self.value})"
