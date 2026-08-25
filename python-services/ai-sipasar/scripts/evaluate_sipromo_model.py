"""Call the configured SiPromo LLM directly with a controlled product context."""

from __future__ import annotations

import asyncio
import json
import time

from sipromo.application.ports.llm import ContextBlock
from sipromo.application.use_cases.prompts import build_llm_request
from sipromo.bootstrap.container import Container
from sipromo.bootstrap.settings import get_settings
from sipromo.domain.entities.promotion_content import PromotionOutput
from sipromo.domain.value_objects.promotion_brief import PromotionBrief


async def main() -> None:
    settings = get_settings()
    container = Container(settings)
    if container.llm is None:
        raise RuntimeError("OpenAI is not configured")

    brief = PromotionBrief.model_validate(
        {
            "objective": "conversion",
            "content_type": "social_media",
            "platform": "instagram",
            "product_ids": ["998ab9c7-c359-4460-8921-b7b7c676c6ea"],
            "target_audience": "Pelanggan lokal yang menyukai camilan pedas",
            "tone": "friendly",
            "language": "id",
            "key_message": "Kenalkan produk secara jujur dan menarik",
            "call_to_action": "Hubungi kami untuk informasi dan pemesanan",
            "constraints": ["Jangan mengarang harga atau diskon"],
        }
    )
    context = ContextBlock(
        block_id="eval-product-1",
        kind="tool_result",
        content=json.dumps(
            {
                "product_id": "998ab9c7-c359-4460-8921-b7b7c676c6ea",
                "name": "Keripik Pedas 100g",
                "status": "in_stock",
                "stock_level": 120,
                "evidence_id": "eval-product-1",
            },
            ensure_ascii=False,
        ),
    )
    request = build_llm_request(
        brief=brief,
        context_blocks=[context],
        tools=[],
        temperature=settings.openai_temperature,
        max_output_tokens=settings.openai_max_output_tokens,
        json_schema=PromotionOutput.model_json_schema(),
    )
    started = time.perf_counter()
    try:
        turn = await container.llm.generate_with_tools(request)
        validated = PromotionOutput.model_validate(turn.final_output)
        result = {
            "scenario": "direct_live_openai_structured_generation",
            "provider": container.llm.provider_name,
            "configured_model": container.llm.model_name,
            "elapsed_seconds": round(time.perf_counter() - started, 3),
            "usage": turn.usage,
            "response": validated.model_dump(mode="json"),
        }
        print(json.dumps(result, ensure_ascii=False, indent=2))
    finally:
        await container.llm._client.aclose()  # noqa: SLF001
        await container.dispose()


if __name__ == "__main__":
    asyncio.run(main())
