"""Run a live, end-to-end evaluation of SiPromo and SiPasar Analytics.

The script reads configuration from the root ``.env`` and prints one JSON
report to stdout. SiPromo's generate endpoint persists a real draft and
generation trace in the configured database.
"""

from __future__ import annotations

import asyncio
import json
import time
import uuid
from typing import Any

from fastapi.testclient import TestClient
from sqlalchemy import text

from main import app
from sipromo.bootstrap.container import Container
from sipromo.bootstrap.settings import get_settings


def timed_request(client: TestClient, method: str, path: str, **kwargs: Any) -> dict[str, Any]:
    started = time.perf_counter()
    try:
        response = client.request(method, path, **kwargs)
        try:
            body: Any = response.json()
        except ValueError:
            body = response.text
        return {
            "method": method,
            "path": path,
            "status_code": response.status_code,
            "elapsed_seconds": round(time.perf_counter() - started, 3),
            "response": body,
        }
    except Exception as exc:
        return {
            "method": method,
            "path": path,
            "elapsed_seconds": round(time.perf_counter() - started, 3),
            "exception": f"{type(exc).__name__}: {exc}",
        }


async def select_test_subject() -> dict[str, Any] | None:
    """Find one active member and in-stock product without exposing secrets."""
    container = Container(get_settings())
    try:
        async with container.session_factory.session() as session:
            result = await session.execute(
                text(
                    """
                    SELECT m.user_id, m.umkm_id, m.role,
                           p.id AS product_id, p.name AS product_name,
                           p.status AS product_status, p.stock_level
                    FROM umkm_memberships AS m
                    JOIN products AS p ON p.umkm_id = m.umkm_id
                    WHERE m.status = 'active'
                      AND m.role IN ('owner', 'staff')
                      AND p.status NOT IN ('out_of_stock', 'inactive', 'discontinued', 'archived')
                      AND COALESCE(p.stock_level, 1) > 0
                    ORDER BY CASE m.role WHEN 'owner' THEN 0 ELSE 1 END, p.created_at DESC
                    LIMIT 1
                    """
                )
            )
            row = result.mappings().first()
            return dict(row) if row else None
    finally:
        await container.dispose()


def main() -> None:
    settings = get_settings()
    subject_started = time.perf_counter()
    try:
        subject = asyncio.run(select_test_subject())
        subject_result: dict[str, Any] = {
            "status": "found" if subject else "not_found",
            "elapsed_seconds": round(time.perf_counter() - subject_started, 3),
        }
    except Exception as exc:
        subject = None
        subject_result = {
            "status": "error",
            "elapsed_seconds": round(time.perf_counter() - subject_started, 3),
            "exception": f"{type(exc).__name__}: {exc}",
        }

    report: dict[str, Any] = {
        "evaluation_id": str(uuid.uuid4()),
        "configuration": {
            "app_env": settings.app_env,
            "auth_enabled": settings.auth_enabled,
            "database_configured": bool(settings.database_url),
            "openai_configured": settings.openai_configured,
            "openai_model": settings.openai_model,
        },
        "database_test_subject": subject_result,
        "results": [],
    }

    analytics_payload = {
        "business_profile_id": "12345678-1234-5678-1234-567812345678",
        "latitude": -7.9666,
        "longitude": 112.6326,
        "category": "kuliner_kopi",
        "radius_meters": 1000,
    }

    try:
        with TestClient(app, raise_server_exceptions=False) as client:
            report["results"].append(timed_request(client, "GET", "/api/v1/health/live"))
            report["results"].append(timed_request(client, "GET", "/api/v1/health/ready"))
            report["results"].append(timed_request(client, "GET", "/v1/health"))
            report["results"].append(
                {
                    "scenario": "live_market_analysis_malang",
                    "request": analytics_payload,
                    **timed_request(client, "POST", "/v1/analysis/run", json=analytics_payload),
                }
            )

            if subject:
                token = app.state.container.jwt_service.create_access_token(
                    user_id=subject["user_id"],
                    umkm_id=subject["umkm_id"],
                    role=subject["role"],
                )
                headers = {
                    "Authorization": f"Bearer {token}",
                    "Idempotency-Key": str(uuid.uuid4()),
                }
                report["database_test_subject"].update(
                    {
                        "role": subject["role"],
                        "umkm_id": str(subject["umkm_id"]),
                        "product_id": str(subject["product_id"]),
                        "product_name": subject["product_name"],
                        "product_status": subject["product_status"],
                        "stock_level": subject["stock_level"],
                    }
                )
                report["results"].append(
                    timed_request(
                        client,
                        "GET",
                        "/api/v1/health/dependencies",
                        headers={"Authorization": f"Bearer {token}"},
                    )
                )
                promotion_payload = {
                    "objective": "conversion",
                    "content_type": "social_media",
                    "platform": "instagram",
                    "product_ids": [str(subject["product_id"])],
                    "target_audience": "Pelanggan lokal yang mencari produk UMKM berkualitas",
                    "tone": "friendly",
                    "language": "id",
                    "key_message": "Kenalkan keunggulan produk secara jujur dan menarik",
                    "call_to_action": "Hubungi kami untuk informasi dan pemesanan",
                    "constraints": [
                        "Jangan mengarang harga atau diskon",
                        "Gunakan hanya fakta yang tersedia",
                    ],
                    "include_market_context": True,
                    "include_business_performance": False,
                }
                report["results"].append(
                    {
                        "scenario": "live_sipromo_generation",
                        "request": promotion_payload,
                        **timed_request(
                            client,
                            "POST",
                            "/api/v1/promotions/generate",
                            json=promotion_payload,
                            headers=headers,
                        ),
                    }
                )
            else:
                report["results"].append(
                    {
                        "scenario": "live_sipromo_generation",
                        "status": "skipped",
                        "reason": (
                            "No active owner/staff membership with an in-stock product was found."
                        ),
                    }
                )
    except Exception as exc:
        report["application_startup_exception"] = f"{type(exc).__name__}: {exc}"

    print(json.dumps(report, ensure_ascii=False, indent=2, default=str))


if __name__ == "__main__":
    main()
