"""Domain exceptions.

Hierarchy:
- DomainError: base for all domain-level errors (carries error_code for API mapping).
- ApplicationError: base for use-case orchestration errors (retryable flag).
- InfrastructureError: base for adapter/provider errors (mapped by presentation).
"""

from __future__ import annotations


class DomainError(Exception):
    """Base error for the domain layer. `error_code` is stable across versions."""

    def __init__(self, message: str, error_code: str = "DOMAIN_ERROR") -> None:
        super().__init__(message)
        self.message = message
        self.error_code = error_code


class TenantMismatchError(DomainError):
    def __init__(self, message: str = "Resource does not belong to the active tenant") -> None:
        super().__init__(message, error_code="TENANT_MISMATCH")


class ResourceNotFoundError(DomainError):
    def __init__(self, resource: str = "resource", error_code: str = "NOT_FOUND") -> None:
        super().__init__(f"{resource} not found", error_code=error_code)


class ProductNotFoundError(ResourceNotFoundError):
    def __init__(self) -> None:
        super().__init__(resource="product", error_code="PRODUCT_NOT_FOUND")


class ProductNotOwnedError(DomainError):
    def __init__(self) -> None:
        super().__init__(
            "Product does not belong to the active tenant", error_code="PRODUCT_NOT_OWNED"
        )


class InvalidPromotionBriefError(DomainError):
    def __init__(self, message: str) -> None:
        super().__init__(message, error_code="INVALID_BRIEF")


class ContentNotFoundError(ResourceNotFoundError):
    def __init__(self) -> None:
        super().__init__(resource="content asset", error_code="CONTENT_NOT_FOUND")


class RevisionNotFoundError(ResourceNotFoundError):
    def __init__(self) -> None:
        super().__init__(resource="revision", error_code="REVISION_NOT_FOUND")


class ApprovalRequiredError(DomainError):
    def __init__(self) -> None:
        super().__init__(
            "An approved revision is required before publishing", error_code="APPROVAL_REQUIRED"
        )


class ApprovalAlreadyDecidedError(DomainError):
    def __init__(self) -> None:
        super().__init__("This content was already decided", error_code="APPROVAL_ALREADY_DECIDED")


class UnauthorizedActionError(DomainError):
    def __init__(self, message: str = "Action not permitted for this role") -> None:
        super().__init__(message, error_code="UNAUTHORIZED_ACTION")


class PublishPlatformError(DomainError):
    def __init__(self, message: str) -> None:
        super().__init__(message, error_code="PUBLISH_PLATFORM_INVALID")


class ClaimViolationError(DomainError):
    """A content claim violates grounding or content-safety policy."""

    def __init__(self, violations: list[str]) -> None:
        super().__init__(
            "Content violates policy: " + "; ".join(violations),
            error_code="CLAIM_VIOLATION",
        )
        self.violations = violations


class KnowledgeDocumentNotFoundError(ResourceNotFoundError):
    def __init__(self) -> None:
        super().__init__(resource="knowledge document", error_code="DOCUMENT_NOT_FOUND")


class UnsupportedFileTypeError(DomainError):
    def __init__(self, mime_type: str) -> None:
        super().__init__(f"Unsupported file type: {mime_type}", error_code="UNSUPPORTED_FILE_TYPE")


class FileTooLargeError(DomainError):
    def __init__(self, max_bytes: int) -> None:
        super().__init__(
            f"File exceeds size limit of {max_bytes} bytes", error_code="FILE_TOO_LARGE"
        )


class ApplicationError(Exception):
    """Base for use-case orchestration errors."""

    def __init__(
        self,
        message: str,
        error_code: str = "APPLICATION_ERROR",
        retryable: bool = False,
    ) -> None:
        super().__init__(message)
        self.message = message
        self.error_code = error_code
        self.retryable = retryable


class InvalidModelResponse(ApplicationError):
    def __init__(self, message: str = "Model returned neither final output nor tool calls") -> None:
        super().__init__(message, error_code="INVALID_MODEL_RESPONSE")


class ToolBudgetExceeded(ApplicationError):
    def __init__(self, limit: int) -> None:
        super().__init__(
            f"Tool call budget exceeded (max {limit} calls)", error_code="TOOL_BUDGET_EXCEEDED"
        )


class ToolRoundLimitExceeded(ApplicationError):
    def __init__(self, limit: int) -> None:
        super().__init__(
            f"Tool round limit exceeded (max {limit} rounds)",
            error_code="TOOL_ROUND_LIMIT_EXCEEDED",
        )


class ProviderQuotaExhausted(ApplicationError):
    def __init__(self) -> None:
        super().__init__(
            "AI provider quota exhausted, please retry later",
            error_code="AI_QUOTA_EXHAUSTED",
            retryable=True,
        )


class ProviderTimeoutError(ApplicationError):
    def __init__(self, provider: str = "AI provider") -> None:
        super().__init__(f"{provider} timed out", error_code="PROVIDER_TIMEOUT", retryable=True)


class InfrastructureError(Exception):
    """Base for infrastructure/adapter failures; never expose internals to clients."""

    def __init__(self, message: str, error_code: str = "INFRASTRUCTURE_ERROR") -> None:
        super().__init__(message)
        self.message = message
        self.error_code = error_code


class StorageError(InfrastructureError):
    def __init__(self, message: str) -> None:
        super().__init__(message, error_code="STORAGE_ERROR")


class DatabaseError(InfrastructureError):
    def __init__(self, message: str) -> None:
        super().__init__(message, error_code="DATABASE_ERROR")


class ConfigurationError(InfrastructureError):
    def __init__(self, message: str) -> None:
        super().__init__(message, error_code="CONFIGURATION_ERROR")
