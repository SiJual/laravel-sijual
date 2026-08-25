#!/usr/bin/env python3
"""
Standalone iterative poster generation script.
Calls the running SiPromo API to generate a promotion, then locally
re-renders the poster using the Pillow compositor with the returned copy.
Saves output to outputs/ for visual inspection.
"""

import json
import os
import sys
import time

import httpx

API_BASE = "http://localhost:8000/api/v1"
HEADERS = {"Content-Type": "application/json"}

BRIEF = {
    "objective": "awareness",
    "content_type": "social_media",
    "platform": "instagram",
    "product_ids": [
        "998ab9c7-c359-4460-8921-b7b7c676c6ea",
        "7f28ed16-0b70-4970-ba6e-e1c7ed55e6dc",
    ],
    "target_audience": "Remaja dan dewasa muda di Bandung yang suka camilan pedas dan produk lokal",
    "tone": "friendly",
    "language": "id",
    "key_message": "Keripik pedas renyah dan batik tulis berkualitas untuk UMKM lokal Indonesia",
    "call_to_action": "Kunjungi toko Kopdes sekarang",
    "constraints": [
        "Jangan menyebut sertifikasi BPOM atau halal",
        "Jangan memakai em-dash",
    ],
    "include_market_context": True,
    "include_business_performance": False,
}

OUTPUT_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "outputs")
os.makedirs(OUTPUT_DIR, exist_ok=True)


def call_generate_api() -> dict:
    print("\n[1/3] Calling POST /api/v1/promotions/generate ...")
    t0 = time.time()
    with httpx.Client(timeout=300) as client:
        resp = client.post(
            f"{API_BASE}/promotions/generate",
            headers=HEADERS,
            json=BRIEF,
        )
    elapsed = time.time() - t0
    print(f"      HTTP {resp.status_code} in {elapsed:.1f}s")
    if resp.status_code not in (200, 201):
        print("ERROR:", resp.text[:2000])
        sys.exit(1)
    return resp.json()


def render_poster_locally(draft: dict, iteration: int) -> str:
    src_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "src")
    if src_dir not in sys.path:
        sys.path.insert(0, src_dir)

    from sipromo.infrastructure.visual.poster.renderer import PillowPosterRenderer

    from sipromo.application.ports.poster_generator import PosterCopy

    copy = PosterCopy(
        headline=draft.get("title", "Promo Spesial")[:24],
        subheadline=draft.get("subheadline", "")[:42],
        description=(draft.get("poster_description") or draft.get("primary_copy", ""))[:120],
        product_label=draft.get("product_label", "")[:40],
        cta=draft.get("call_to_action", "Kunjungi Sekarang")[:28],
        hashtags=draft.get("hashtags", [])[:5],
    )

    print(f"\n[2/3] Rendering poster locally (iteration {iteration}) ...")
    print(f"      Headline   : {copy.headline!r}")
    print(f"      Subheadline: {copy.subheadline!r}")
    print(f"      Description: {copy.description!r}")
    print(f"      Label      : {copy.product_label!r}")
    print(f"      CTA        : {copy.cta!r}")
    print(f"      Hashtags   : {copy.hashtags}")

    renderer = PillowPosterRenderer(width=1080, height=1350)
    png_bytes, report = renderer.render(copy, None)

    out_path = os.path.join(OUTPUT_DIR, f"poster_iter_{iteration:02d}.png")
    with open(out_path, "wb") as f:
        f.write(png_bytes)

    print(f"\n[3/3] Saved -> {out_path}")
    print(f"      layout_valid={report.layout_valid}  overflow={report.has_overflow}")
    if report.warnings:
        print(f"      warnings: {report.warnings}")
    return out_path


def main():
    draft = call_generate_api()

    print("\n--- LLM Output ---")
    for k in (
        "title",
        "subheadline",
        "primary_copy",
        "poster_description",
        "product_label",
        "call_to_action",
        "hashtags",
        "visual_brief",
    ):
        print(f"  {k}: {draft.get(k)}")
    print("------------------")

    iteration = int(sys.argv[1]) if len(sys.argv) > 1 else 1
    out_path = render_poster_locally(draft, iteration)
    print(f"\nDone! Open: {out_path}")

    json_path = os.path.join(OUTPUT_DIR, f"draft_iter_{iteration:02d}.json")
    with open(json_path, "w") as f:
        json.dump(draft, f, indent=2, ensure_ascii=False)
    print(f"Draft JSON: {json_path}")


if __name__ == "__main__":
    main()
