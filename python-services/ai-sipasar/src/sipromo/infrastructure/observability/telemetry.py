"""Structured logging and request context. Sensitive values are never logged;
user/tenant IDs are short-hashed in non-debug modes."""

from __future__ import annotations

import hashlib
import logging
import uuid
from contextvars import ContextVar

import structlog

_request_id_var: ContextVar[str] = ContextVar("sipromo_request_id", default="-")
_actor_var: ContextVar[str | None] = ContextVar("sipromo_actor", default=None)


def get_request_id() -> str:
    return _request_id_var.get()


def set_request_id(request_id: str) -> None:
    _request_id_var.set(request_id)


def set_actor(actor_label: str | None) -> None:
    _actor_var.set(actor_label)


def obfuscate(value: str | object | None) -> str:
    """Short-hash an identifier for logs (user_id/umkm_id)."""
    if value is None:
        return "-"
    raw = str(value)
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()[:10]


def setup_logging(level: str = "INFO") -> None:
    structlog.configure(
        processors=[
            structlog.contextvars.merge_contextvars,
            structlog.processors.add_log_level,
            structlog.processors.TimeStamper(fmt="iso"),
            structlog.processors.StackInfoRenderer(),
            structlog.processors.format_exc_info,
            structlog.processors.JSONRenderer(),
        ],
        wrapper_class=structlog.make_filtering_bound_logger(
            getattr(logging, level.upper(), logging.INFO)
        ),
        logger_factory=structlog.PrintLoggerFactory(),
        cache_logger_on_first_use=True,
    )
    logging.basicConfig(level=getattr(logging, level.upper(), logging.INFO))


def log_request(
    logger_name: str,
    event: str,
    *,
    request_id: str,
    user: str | None,
    umkm: str | None,
    run_id: str | None = None,
    **extra: object,
) -> None:
    log = structlog.get_logger(logger_name)
    log.info(
        event,
        request_id=request_id,
        user_id=obfuscate(user),
        umkm_id=obfuscate(umkm),
        generation_run_id=obfuscate(run_id),
        **extra,
    )


def new_request_id() -> str:
    return str(uuid.uuid4())
