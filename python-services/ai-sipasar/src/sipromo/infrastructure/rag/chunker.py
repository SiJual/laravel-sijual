"""Chunking pipeline: heading/paragraph-first splitting with token targets,
overlap, and normalized-text deduplication (section 12.3)."""

from __future__ import annotations

import hashlib
import re
import uuid
from dataclasses import dataclass

from sipromo.application.use_cases.ingest_knowledge import ChunkerPort
from sipromo.domain.entities.knowledge_document import KnowledgeChunk

_HEADING_RE = re.compile(r"^(#{1,6}\s+.+|.+\n[=\-]{3,}\s*)$", re.MULTILINE)
_PARAGRAPH_SPLIT_RE = re.compile(r"\n\s*\n")
_SENTENCE_SPLIT_RE = re.compile(r"(?<=[.!?])\s+|\n+")

# Rough heuristic: 1 token ~= 4 chars for Indonesian/English prose.
TOKENS_PER_CHAR = 0.25


@dataclass
class ChunkingConfig:
    target_min_tokens: int = 500
    target_max_tokens: int = 800
    overlap_tokens: int = 100

    @property
    def min_chars(self) -> int:
        return int(self.target_min_tokens / TOKENS_PER_CHAR)

    @property
    def max_chars(self) -> int:
        return int(self.target_max_tokens / TOKENS_PER_CHAR)

    @property
    def overlap_chars(self) -> int:
        return int(self.overlap_tokens / TOKENS_PER_CHAR)


@dataclass
class _Section:
    heading: str | None
    body: str


def estimate_tokens(text: str) -> int:
    return max(1, int(len(text) * TOKENS_PER_CHAR))


def normalize_text_hash(text: str) -> str:
    normalized = re.sub(r"\s+", " ", text).strip().lower()
    return hashlib.sha256(normalized.encode("utf-8")).hexdigest()


class Chunker(ChunkerPort):
    def __init__(self, config: ChunkingConfig | None = None) -> None:
        self._config = config or ChunkingConfig()

    def chunk(
        self, text: str, *, document_id: str, umkm_id: str, title: str
    ) -> list[KnowledgeChunk]:
        sections = self._split_sections(text)
        raw_chunks: list[tuple[str, dict]] = []
        for section in sections:
            raw_chunks.extend(self._split_body(section))
        # Deduplication by normalized hash (stable within document).
        seen: set[str] = set()
        chunks: list[KnowledgeChunk] = []
        index = 0
        for content, meta in raw_chunks:
            digest = normalize_text_hash(content)
            if digest in seen:
                continue
            seen.add(digest)
            chunks.append(
                KnowledgeChunk(
                    chunk_id=str(uuid.uuid4()),
                    document_id=document_id,
                    umkm_id=umkm_id,
                    chunk_index=index,
                    content=content,
                    token_count=estimate_tokens(content),
                    embedding=[],
                    metadata={**meta, "document_title": title},
                )
            )
            index += 1
        return chunks

    # ------------------------------------------------------------------ #

    def _split_sections(self, text: str) -> list[_Section]:
        text = text.strip()
        if not text:
            return []
        matches = list(_HEADING_RE.finditer(text))
        if not matches:
            return [_Section(heading=None, body=text)]
        sections: list[_Section] = []
        for i, match in enumerate(matches):
            start = match.end()
            end = matches[i + 1].start() if i + 1 < len(matches) else len(text)
            body = text[start:end].strip()
            if not body:
                continue
            heading = match.group(0).strip().replace("\n", " ").strip()
            sections.append(_Section(heading=heading, body=body))
        return sections

    def _split_body(self, section: _Section) -> list[tuple[str, dict]]:
        meta: dict = {}
        if section.heading:
            meta["heading"] = section.heading
        body = section.body
        if not body:
            return []
        if len(body) <= self._config.max_chars:
            return [(body, meta)]

        paragraphs = [p.strip() for p in _PARAGRAPH_SPLIT_RE.split(body) if p.strip()]
        result: list[tuple[str, dict]] = []
        current: list[str] = []
        current_len = 0
        overlap_tail: list[str] = []

        def flush() -> None:
            nonlocal current, current_len, overlap_tail
            if not current:
                return
            text = "\n\n".join(current)
            merged = {**meta, "overlap": bool(overlap_tail)}
            result.append((text, merged))
            # Keep trailing sentences as overlap window.
            tail: list[str] = []
            tail_len = 0
            for paragraph in reversed(current):
                sentences = _SENTENCE_SPLIT_RE.split(paragraph)
                for sentence in reversed(sentences):
                    if tail_len + len(sentence) > self._config.overlap_chars:
                        break
                    tail.insert(0, sentence)
                    tail_len += len(sentence) + 1
            overlap_tail = tail
            current = []
            current_len = 0

        for paragraph in paragraphs:
            if paragraph in overlap_tail:
                overlap_tail.remove(paragraph)
            estimated = len(paragraph)
            if current_len + estimated > self._config.max_chars and current:
                flush()
            if overlap_tail:
                current = list(overlap_tail)
                current_len = sum(len(p) for p in current) + 2 * len(current)
                overlap_tail = []
            current.append(paragraph)
            current_len += estimated
        flush()
        return result
