"""Exception handlers: stable error envelope without stack traces or secrets."""

from __future__ import annotations

import logging

from fastapi import FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from pydantic import ValidationError

from sipromo.domain.exceptions import (
    ApplicationError,
    ApprovalAlreadyDecidedError,
    ApprovalRequiredError,
    ClaimViolationError,
    ConfigurationError,
    DomainError,
    FileTooLargeError,
    InfrastructureError,
    KnowledgeDocumentNotFoundError,
    ProductNotFoundError,
    ResourceNotFoundError,
    TenantMismatchError,
    UnauthorizedActionError,
    UnsupportedFileTypeError,
)
from sipromo.infrastructure.observability.telemetry import get_request_id

logger = logging.getLogger(__name__)

_STATUS_BY_ERROR = {
    TenantMismatchError: 403,
    UnauthorizedActionError: 403,
    ProductNotFoundError: 422,
    KnowledgeDocumentNotFoundError: 404,
    ResourceNotFoundError: 404,
    ApprovalAlreadyDecidedError: 409,
    ApprovalRequiredError: 409,
    ClaimViolationError: 422,
    UnsupportedFileTypeError: 415,
    FileTooLargeError: 413,
}


def _envelope(status: int, code: str, message: str, details: list | None = None) -> JSONResponse:
    body: dict = {
        "error": {
            "code": code,
            "message": message,
            "request_id": get_request_id(),
        }
    }
    if details:
        body["error"]["details"] = details
    return JSONResponse(status_code=status, content=body)


def register_exception_handlers(app: FastAPI) -> None:
    @app.exception_handler(DomainError)
    async def domain_error_handler(request: Request, exc: DomainError) -> JSONResponse:
        status = _STATUS_BY_ERROR.get(type(exc), 400)
        details = getattr(exc, "violations", None) or None
        return _envelope(status, exc.error_code, exc.message, details)

    @app.exception_handler(ApplicationError)
    async def application_error_handler(request: Request, exc: ApplicationError) -> JSONResponse:
        status = 503 if exc.retryable else 422
        return _envelope(status, exc.error_code, exc.message)

    @app.exception_handler(InfrastructureError)
    async def infrastructure_error_handler(
        request: Request, exc: InfrastructureError
    ) -> JSONResponse:
        logger.error("infrastructure error", extra={"code": exc.error_code})
        return _envelope(500, "INTERNAL_ERROR", "internal server error")

    @app.exception_handler(ConfigurationError)
    async def configuration_error_handler(
        request: Request, exc: ConfigurationError
    ) -> JSONResponse:
        return _envelope(503, exc.error_code, exc.message)

    @app.exception_handler(ValidationError)
    async def pydantic_error_handler(request: Request, exc: ValidationError) -> JSONResponse:
        details = [
            {"loc": ".".join(str(p) for p in e["loc"]), "msg": e["msg"]} for e in exc.errors()
        ]
        return _envelope(422, "VALIDATION_ERROR", "invalid input", details)

    @app.exception_handler(RequestValidationError)
    async def request_validation_error_handler(
        request: Request, exc: RequestValidationError
    ) -> JSONResponse:
        details = [
            {"loc": ".".join(str(p) for p in e["loc"]), "msg": e["msg"]} for e in exc.errors()
        ]
        return _envelope(422, "VALIDATION_ERROR", "invalid request", details)

    @app.exception_handler(ValueError)
    async def value_error_handler(request: Request, exc: ValueError) -> JSONResponse:
        return _envelope(400, "BAD_REQUEST", str(exc)[:300])

    @app.exception_handler(Exception)
    async def unhandled_error_handler(request: Request, exc: Exception) -> JSONResponse:
        logger.exception("unhandled error")
        return _envelope(500, "INTERNAL_ERROR", "internal server error")
