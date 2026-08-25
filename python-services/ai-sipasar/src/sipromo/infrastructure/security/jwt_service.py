"""JWT service: access token creation and validation.

Legacy passwords in users.password are treated as opaque hashes - never logged
and never sent to the AI. Tenant authorization is always verified against the
database via memberships, not trusted from the token alone.
"""

from __future__ import annotations

from datetime import UTC, datetime, timedelta
from uuid import UUID

from jose import JWTError, jwt
from pydantic import BaseModel

from sipromo.domain.exceptions import InfrastructureError


class TokenClaims(BaseModel):
    sub: str
    user_id: UUID
    umkm_id: UUID | None = None
    role: str | None = None
    exp: int


class JwtService:
    def __init__(self, secret: str, algorithm: str, expire_minutes: int) -> None:
        self._secret = secret
        self._algorithm = algorithm
        self._expire_minutes = expire_minutes

    def create_access_token(self, *, user_id: UUID, umkm_id: UUID, role: str) -> str:
        now = datetime.now(UTC)
        payload = {
            "sub": str(user_id),
            "user_id": str(user_id),
            "umkm_id": str(umkm_id),
            "role": role,
            "iat": int(now.timestamp()),
            "exp": int((now + timedelta(minutes=self._expire_minutes)).timestamp()),
        }
        return jwt.encode(payload, self._secret, algorithm=self._algorithm)

    def decode(self, token: str) -> TokenClaims:
        """Decode and verify a token's signature/expiry.

        Tokens carrying only standard claims (``sub``/``iat``/``exp``) are
        accepted: tenant and role are resolved against the database via
        active memberships, never trusted from the token alone.
        """
        try:
            payload = jwt.decode(token, self._secret, algorithms=[self._algorithm])
            subject = payload["sub"]
            user_id = payload.get("user_id", subject)
            return TokenClaims(
                sub=subject,
                user_id=UUID(user_id),
                umkm_id=UUID(payload["umkm_id"]) if payload.get("umkm_id") else None,
                role=payload.get("role"),
                exp=int(payload["exp"]),
            )
        except (JWTError, KeyError, ValueError) as exc:
            raise InfrastructureError(
                "invalid or expired token", error_code="INVALID_TOKEN"
            ) from exc
