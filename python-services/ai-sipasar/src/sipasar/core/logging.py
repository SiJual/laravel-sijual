"""SiPasar — Structured JSON logging with request/analysis tracing."""

import logging
import sys
import uuid
from contextvars import ContextVar
from datetime import UTC, datetime

# Context variable to carry trace IDs across async boundaries
_request_id_var: ContextVar[str] = ContextVar("request_id", default="")
_analysis_id_var: ContextVar[str] = ContextVar("analysis_id", default="")


def get_request_id() -> str:
    return _request_id_var.get() or str(uuid.uuid4())


def set_request_id(request_id: str) -> None:
    _request_id_var.set(request_id)


def set_analysis_id(analysis_id: str) -> None:
    _analysis_id_var.set(analysis_id)


class StructuredFormatter(logging.Formatter):
    """Format log records as JSON-like structured strings."""

    def format(self, record: logging.LogRecord) -> str:  # noqa: A003
        log_entry = {
            "timestamp": datetime.now(UTC).isoformat(),
            "level": record.levelname,
            "logger": record.name,
            "message": record.getMessage(),
            "request_id": _request_id_var.get() or None,
            "analysis_id": _analysis_id_var.get() or None,
        }
        if record.exc_info:
            log_entry["exception"] = self.formatException(record.exc_info)

        import json

        return json.dumps(log_entry, ensure_ascii=False)


def setup_logging(level: str = "INFO") -> None:
    """Configure root logger with structured JSON output."""
    root = logging.getLogger()
    root.setLevel(getattr(logging, level.upper(), logging.INFO))

    handler = logging.StreamHandler(sys.stdout)
    handler.setFormatter(StructuredFormatter())

    # Remove existing handlers to avoid duplication
    root.handlers.clear()
    root.addHandler(handler)

    # Suppress noisy third-party loggers
    for noisy in ("httpx", "httpcore", "uvicorn.access"):
        logging.getLogger(noisy).setLevel(logging.WARNING)


def get_logger(name: str) -> logging.Logger:
    return logging.getLogger(name)
