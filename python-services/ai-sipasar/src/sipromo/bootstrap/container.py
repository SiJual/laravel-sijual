"""Manual dependency injection container (bootstrap layer).

Wire all adapters behind application ports. No framework code reaches the
domain; this module is the only place that knows concrete implementations.
"""

from __future__ import annotations

import logging

from sipromo.application.policies.content_safety import DeterministicContentPolicy
from sipromo.application.ports.object_storage import StoredAsset, UploadAsset
from sipromo.application.use_cases.approve_content import ApproveContentUseCase
from sipromo.application.use_cases.generate_promotion import GeneratePromotionUseCase
from sipromo.application.use_cases.ingest_knowledge import IngestKnowledgeUseCase
from sipromo.application.use_cases.publish_content import PublishContentUseCase
from sipromo.application.use_cases.revise_content import ReviseContentUseCase
from sipromo.bootstrap.settings import Settings
from sipromo.domain.exceptions import ConfigurationError
from sipromo.infrastructure.ai.openai_compatible_adapter import OpenAICompatibleAdapter
from sipromo.infrastructure.ai.openai_embeddings_adapter import OpenAIEmbeddingAdapter
from sipromo.infrastructure.db.repositories.business_repository import BusinessRepository
from sipromo.infrastructure.db.repositories.content_repository import (
    ContentRepository,
    RunRepositoryImpl,
)
from sipromo.infrastructure.db.repositories.knowledge_repository import KnowledgeRepository
from sipromo.infrastructure.db.repositories.misc_repositories import (
    IdempotencyRepositoryImpl,
    MembershipRepositoryImpl,
    PublishJobRepository,
)
from sipromo.infrastructure.db.session import SessionFactory, SqlAlchemyUnitOfWork
from sipromo.infrastructure.rag.chunker import Chunker
from sipromo.infrastructure.rag.hybrid_retriever import HybridRetriever
from sipromo.infrastructure.rag.text_extractor import TextExtractor
from sipromo.infrastructure.security.jwt_service import JwtService
from sipromo.infrastructure.storage.cloudinary_adapter import CloudinaryAdapter, CloudinaryConfig
from sipromo.infrastructure.tools.read_tools import register_read_tools
from sipromo.infrastructure.tools.registry import ToolExecutor, ToolRegistry
from sipromo.infrastructure.tools.write_tools import register_write_tools
from sipromo.infrastructure.visual.openai_image_poster import OpenAIImagePosterGenerator

logger = logging.getLogger(__name__)


class Container:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings

        # Database.
        self.session_factory = SessionFactory(settings)
        self.unit_of_work = SqlAlchemyUnitOfWork(self.session_factory)

        # Repositories.
        self.business_repo = BusinessRepository()
        self.knowledge_repo = KnowledgeRepository()
        self.content_repo = ContentRepository()
        self.run_repo = RunRepositoryImpl()
        self.membership_repo = MembershipRepositoryImpl()
        self.idempotency_repo = IdempotencyRepositoryImpl()
        self.publish_job_repo = PublishJobRepository()

        # Security.
        self.jwt_service = JwtService(
            secret=settings.jwt_secret,
            algorithm=settings.jwt_algorithm,
            expire_minutes=settings.access_token_expire_minutes,
        )

        # AI providers (OpenAI only; model swap via settings).
        self.llm: OpenAICompatibleAdapter | None = None
        self.embeddings: OpenAIEmbeddingAdapter | None = None
        self.storage: CloudinaryAdapter | None = None
        self.poster_generator: OpenAIImagePosterGenerator | None = None
        if settings.openai_configured:
            self.llm = OpenAICompatibleAdapter(
                api_key=settings.openai_api_key,
                model=settings.openai_model,
                temperature=settings.openai_temperature,
                max_output_tokens=settings.openai_max_output_tokens,
                base_url=settings.openai_base_url,
                provider_name="openai",
                reasoning_effort=settings.openai_reasoning_effort,
                timeout_ms=settings.openai_request_timeout_seconds * 1000,
            )
            self.poster_generator = OpenAIImagePosterGenerator(
                api_key=settings.openai_api_key,
                model=settings.openai_image_model,
                size=settings.openai_image_size,
                quality=settings.openai_image_quality,
                timeout_seconds=settings.openai_image_timeout_seconds,
            )
            self.embeddings = OpenAIEmbeddingAdapter(
                api_key=settings.openai_api_key,
                model=settings.openai_embedding_model,
                dimension=settings.embedding_dim,
            )

        if settings.cloudinary_configured:
            self.storage = CloudinaryAdapter(
                CloudinaryConfig(
                    cloud_name=settings.cloudinary_cloud_name,
                    api_key=settings.cloudinary_api_key,
                    api_secret=settings.cloudinary_api_secret,
                )
            )
        else:
            self.storage = None

        # RAG.
        self.chunker = Chunker()
        self.text_extractor = TextExtractor()
        self.retriever: HybridRetriever | None = None

        # Tools.
        self.tool_registry = ToolRegistry()
        self.tool_executor = ToolExecutor(self.tool_registry)

        # Policies.
        self.content_policy = DeterministicContentPolicy()

    def configure_tools(self) -> None:
        if getattr(self, "_tools_configured", False):
            return
        if self.retriever is not None:
            register_read_tools(
                self.tool_registry,
                business_repo=self.business_repo,
                retriever=self.retriever,
            )
        register_write_tools(self.tool_registry)
        self._tools_configured = True

    def validate_ai_configuration(self) -> None:
        """Startup validation (section 10.1): fail fast when AI is misconfigured."""
        if self.settings.app_env in {"test", "development-without-ai"}:
            return
        missing = []
        if not self.settings.openai_configured:
            missing.append("OPENAI_API_KEY")
        if not self.settings.openai_model:
            missing.append("OPENAI_MODEL")
        if missing:
            raise ConfigurationError("AI provider configuration incomplete: " + ", ".join(missing))

    def build_use_cases(self) -> None:
        """Construct use cases after all adapters are registered."""
        if getattr(self, "_use_cases_built", False):
            return
        if self.llm is None or self.embeddings is None:
            raise ConfigurationError(
                "AI provider not configured; cannot build generation use cases"
            )
        self.retriever = HybridRetriever(
            embeddings=self.embeddings, knowledge_repo=self.knowledge_repo
        )
        self.configure_tools()
        self.ingest_knowledge = IngestKnowledgeUseCase(
            unit_of_work=self.unit_of_work,
            storage=self.storage if self.storage is not None else _UnavailableStorage(),
            extractor=self.text_extractor,
            chunker=self.chunker,
            embeddings=self.embeddings,
            knowledge_read=self.knowledge_repo,
            knowledge_write=self.knowledge_repo,
            upload_max_bytes=self.settings.upload_max_bytes,
        )
        self.generate_promotion = GeneratePromotionUseCase(
            unit_of_work=self.unit_of_work,
            retriever=self.retriever,
            embeddings=self.embeddings,
            llm=self.llm,
            tool_executor=self.tool_executor,
            tool_registry=self.tool_registry,
            business_repo=self.business_repo,
            knowledge_repo=self.knowledge_repo,
            content_read=self.content_repo,
            content_write=self.content_repo,
            run_repo=self.run_repo,
            content_policy=self.content_policy,
            ai_max_tool_rounds=self.settings.ai_max_tool_rounds,
            ai_max_tool_calls=self.settings.ai_max_tool_calls,
            llm_run_max_total_tokens=self.settings.llm_run_max_total_tokens,
            poster_generator=self.poster_generator,
            storage=self.storage,
        )
        self.revise_content = ReviseContentUseCase(
            unit_of_work=self.unit_of_work,
            content_read=self.content_repo,
            content_write=self.content_repo,
        )
        self.approve_content = ApproveContentUseCase(
            unit_of_work=self.unit_of_work,
            content_read=self.content_repo,
            content_write=self.content_repo,
        )
        self.publish_content = PublishContentUseCase(
            unit_of_work=self.unit_of_work,
            content_read=self.content_repo,
            publish_job_repo=self.publish_job_repo,
        )
        self._use_cases_built = True

    async def dispose(self) -> None:
        await self.session_factory.dispose()


class _UnavailableStorage:
    """Fallback storage that fails loudly when Cloudinary is not configured."""

    async def upload(self, asset: UploadAsset) -> StoredAsset:
        raise ConfigurationError(
            "Cloudinary tidak dikonfigurasi; upload knowledge dokumen tidak tersedia"
        )

    async def delete(self, public_id: str) -> None:
        return None
