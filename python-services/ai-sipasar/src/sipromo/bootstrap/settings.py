from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Application configuration. Values come from environment / .env file."""

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
        case_sensitive=False,
    )

    app_env: str = "development"
    app_name: str = "sipromo-contextual-promotion"
    api_v1_prefix: str = "/api/v1"
    log_level: str = "INFO"

    database_url: str = "postgresql+asyncpg://sipromo:sipromo@localhost:5432/sipromo"
    database_pool_size: int = 5
    database_max_overflow: int = 5

    jwt_secret: str = "change-me-in-production"  # noqa: S105
    jwt_algorithm: str = "HS256"
    access_token_expire_minutes: int = 30

    auth_enabled: bool = True
    auth_disabled_user_id: str = "01a00669-10a3-7322-9527-51640fc861ec"
    auth_disabled_umkm_id: str = "01a00669-68a8-7376-890b-f3b6ff05bf17"
    auth_disabled_role: str = "owner"

    embedding_dim: int = 768
    ai_max_tool_rounds: int = 6
    ai_max_tool_calls: int = 16

    openai_api_key: str = ""
    openai_model: str = "gpt-5-mini"
    openai_base_url: str = "https://api.openai.com/v1"
    openai_temperature: float = 0.3
    openai_max_output_tokens: int = 6000
    openai_reasoning_effort: str = "low"
    openai_request_timeout_seconds: int = 180
    openai_embedding_model: str = "text-embedding-3-small"
    openai_image_model: str = "gpt-image-1"
    openai_image_size: str = "1024x1024"
    openai_image_quality: str = "medium"
    openai_image_timeout_seconds: int = 120

    llm_run_max_total_tokens: int = 60_000

    cloudinary_cloud_name: str = ""
    cloudinary_api_key: str = ""
    cloudinary_api_secret: str = ""

    rag_top_k_vector: int = 12
    rag_top_k_lexical: int = 12
    rag_final_k: int = 8
    rag_min_score: float = 0.55
    rag_max_context_tokens: int = 6000

    upload_max_bytes: int = 10 * 1024 * 1024

    @property
    def openai_configured(self) -> bool:
        return bool(self.openai_api_key)

    @property
    def cloudinary_configured(self) -> bool:
        return bool(
            self.cloudinary_cloud_name and self.cloudinary_api_key and self.cloudinary_api_secret
        )


@lru_cache
def get_settings() -> Settings:
    return Settings()
