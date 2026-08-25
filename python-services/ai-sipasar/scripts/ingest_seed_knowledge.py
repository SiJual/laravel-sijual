"""Seed a tenant's knowledge base from a directory of text/markdown files.

Usage:
    DATABASE_URL=... GEMINI_API_KEY=... python -m scripts.ingest_seed_knowledge \
        --umkm-id <uuid> --dir knowledge-seed

File-to-document-type mapping (most specific wins):
  1. A subdirectory named after a DocumentType value ("brand_guide", "faq", ...).
  2. A filename prefix "brand_guide__anything.md" (before the first "__").
  3. Otherwise the --type flag (default "other").

Idempotent: files whose content was already ingested are skipped.
"""

from __future__ import annotations

import argparse
import asyncio
import sys
from pathlib import Path
from uuid import UUID

from sipromo.application.dto.promotion_requests import (
    AuthenticatedActor,
    IngestTextKnowledgeCommand,
)
from sipromo.bootstrap.container import Container
from sipromo.bootstrap.settings import Settings
from sipromo.domain.value_objects.content_type import DocumentType

KNOWN_TYPES = {t.value: t for t in DocumentType}


def _document_type_for(path: Path, fallback: DocumentType) -> DocumentType:
    for part in path.parts:
        if part in KNOWN_TYPES:
            return KNOWN_TYPES[part]
    stem = path.stem.split("__", 1)[0]
    if stem in KNOWN_TYPES:
        return KNOWN_TYPES[stem]
    return fallback


async def run(umkm_id: str, seed_dir: Path, fallback: DocumentType) -> int:
    if not seed_dir.is_dir():  # noqa: ASYNC240 - local seed corpus
        print(f"seed directory not found: {seed_dir}", file=sys.stderr)
        return 2

    container = Container(Settings())
    container.build_use_cases()
    actor = AuthenticatedActor(
        user_id=UUID(umkm_id),
        umkm_id=UUID(umkm_id),
        role="owner",
    )

    files = sorted(
        p
        for p in seed_dir.rglob("*")  # noqa: ASYNC240 - local seed corpus
        if p.is_file() and p.suffix.lower() in {".md", ".txt"}
    )
    if not files:
        print(f"no .md/.txt files under {seed_dir}", file=sys.stderr)
        return 2

    skipped = 0
    for path in files:
        doc_type = _document_type_for(path, fallback)
        text = path.read_text(encoding="utf-8")  # noqa: ASYNC240 - local seed corpus
        command = IngestTextKnowledgeCommand(
            title=f"{path.stem} ({doc_type.value})",
            document_type=doc_type,
            text=text,
        )
        try:
            result = await container.ingest_knowledge.ingest_text(command, actor)
            print(f"ingested {path} -> {result.document_id} [{doc_type.value}]")
        except Exception as exc:  # noqa: BLE001 - report and continue
            code = getattr(exc, "error_code", type(exc).__name__)
            if code == "DUPLICATE_DOCUMENT":
                skipped += 1
                print(f"skip    {path} (duplicate)")
            else:
                print(f"error   {path}: {exc}", file=sys.stderr)
                return 1

    print(f"done: {len(files) - skipped} ingested, {skipped} skipped")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--umkm-id", required=True, help="tenant (UMKM) UUID")
    parser.add_argument("--dir", required=True, help="directory with seed documents")
    parser.add_argument(
        "--type",
        dest="doc_type",
        default="other",
        choices=sorted(KNOWN_TYPES),
        help="default document type",
    )
    args = parser.parse_args()

    container = Container(Settings())
    if not container.settings.gemini_configured:
        print("GEMINI_API_KEY is required to embed seed documents", file=sys.stderr)
        return 2

    return asyncio.run(run(args.umkm_id, Path(args.dir), KNOWN_TYPES[args.doc_type]))


if __name__ == "__main__":
    sys.exit(main())
