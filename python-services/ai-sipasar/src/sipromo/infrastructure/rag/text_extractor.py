"""Text extraction per MIME type. Uploaded content is untrusted data."""

from __future__ import annotations

from sipromo.application.use_cases.ingest_knowledge import TextExtractorPort


class TextExtractor(TextExtractorPort):
    async def extract(self, content: bytes, mime_type: str) -> str:
        if mime_type in {"text/plain", "text/markdown", "text/csv"}:
            return content.decode("utf-8", errors="replace")
        if mime_type == "application/pdf":
            return await self._extract_pdf(content)
        if mime_type in {
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "application/msword",
        }:
            return self._extract_docx(content)
        raise ValueError(f"unsupported mime type: {mime_type}")

    @staticmethod
    async def _extract_pdf(content: bytes) -> str:
        import io

        from pypdf import PdfReader

        reader = PdfReader(io.BytesIO(content))
        pages = []
        for page in reader.pages:
            text = page.extract_text() or ""
            pages.append(text)
        return "\n\n".join(pages)

    @staticmethod
    def _extract_docx(content: bytes) -> str:
        import io

        try:
            from docx import Document
        except ImportError as exc:  # pragma: no cover
            raise ValueError("docx support unavailable") from exc

        document = Document(io.BytesIO(content))
        paragraphs = [p.text for p in document.paragraphs if p.text.strip()]
        return "\n\n".join(paragraphs)
