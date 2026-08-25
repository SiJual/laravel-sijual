from enum import StrEnum


class Objective(StrEnum):
    AWARENESS = "awareness"
    ENGAGEMENT = "engagement"
    CONVERSION = "conversion"
    RETENTION = "retention"


class ContentType(StrEnum):
    SOCIAL_MEDIA = "social_media"
    AD_COPY = "ad_copy"
    BLOG_POST = "blog_post"
    EMAIL = "email"


class Platform(StrEnum):
    INSTAGRAM = "instagram"
    FACEBOOK = "facebook"
    GENERIC = "generic"


class Tone(StrEnum):
    FRIENDLY = "friendly"
    PROFESSIONAL = "professional"
    PLAYFUL = "playful"
    PREMIUM = "premium"
    EDUCATIONAL = "educational"


class Language(StrEnum):
    ID = "id"
    EN = "en"


class DocumentType(StrEnum):
    BRAND_GUIDE = "brand_guide"
    PRODUCT_CATALOG = "product_catalog"
    FAQ = "faq"
    CAMPAIGN_EXAMPLE = "campaign_example"
    POLICY = "policy"
    OTHER = "other"


class DocumentStatus(StrEnum):
    PENDING = "pending"
    PROCESSING = "processing"
    READY = "ready"
    FAILED = "failed"
    ARCHIVED = "archived"


class SourceKind(StrEnum):
    RAG_CHUNK = "rag_chunk"
    TOOL_RESULT = "tool_result"
    USER_INPUT = "user_input"
    SYSTEM_RULE = "system_rule"


class ApprovalDecision(StrEnum):
    APPROVED = "approved"
    REJECTED = "rejected"
    CHANGES_REQUESTED = "changes_requested"


class RunStatus(StrEnum):
    STARTED = "started"
    COMPLETED = "completed"
    FAILED = "failed"
    REJECTED = "rejected"
