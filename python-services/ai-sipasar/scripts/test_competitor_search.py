"""Search real nearby competitors through the configured SiPasar provider chain."""

from __future__ import annotations

import argparse
import asyncio
import json
from dataclasses import asdict

from sipasar.services.competitor_service import CompetitorService


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--lat", type=float, default=-7.9666)
    parser.add_argument("--lon", type=float, default=112.6326)
    parser.add_argument("--category", default="kuliner_kopi")
    parser.add_argument("--radius", type=int, default=1000)
    return parser.parse_args()


async def run() -> None:
    args = parse_args()
    service = CompetitorService()
    try:
        result = await service.analyze(
            lat=args.lat,
            lon=args.lon,
            category=args.category,
            radius_meters=args.radius,
        )
        print(json.dumps(asdict(result), ensure_ascii=False, indent=2))
    finally:
        await service.aclose()


if __name__ == "__main__":
    asyncio.run(run())
