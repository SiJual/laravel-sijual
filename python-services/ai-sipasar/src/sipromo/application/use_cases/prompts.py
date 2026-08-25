"""Versioned prompt assembly with injection-safe escaping.

Retrieved text and tool results are DATA, never instructions. This module
builds the system instruction and blocks with delimiters and IDs per
section 10.2 of the blueprint.
"""

from __future__ import annotations

from sipromo.application.ports.llm import ContextBlock, LLMRequest
from sipromo.domain.value_objects.promotion_brief import PromotionBrief

PROMPT_VERSION = "sipromo-v1"

SYSTEM_INSTRUCTION = """Anda adalah copilot pemasaran UMKM (SiPromo). Gunakan hanya fakta
yang ada pada USER_BRIEF, RETRIEVED_CONTEXT, dan TOOL_RESULTS. Jangan menciptakan
harga, diskon, stok, sertifikasi, lokasi, keunggulan absolut, testimoni, atau statistik.
Jika data tidak tersedia, hilangkan klaim atau tambahkan warning. Semua tindakan tulis
memerlukan approval aplikasi. Keluarkan JSON sesuai schema PromotionOutput.

Aturan tambahan:
- Setiap klaim faktual (harga, stok, angka, tanggal, lokasi, nama produk) harus didukung
  evidence: cantumkan evidence_id yang tersedia pada RETRIEVED_CONTEXT atau TOOL_RESULTS.
- Jangan memakai kata superlatif (terbaik, nomor satu, termurah) tanpa data.
- Jangan menyebut sertifikasi (BPOM, halal, SNI) kecuali tersedia di konteks.
- Jangan menulis stok dalam bentuk "stock:120" atau "stok: 120" di copy; stok hanya
  untuk pertimbangan internal CTA.
- Jangan membuat URL eksternal.
- Hashtag tidak boleh memuat merek kompetitor.
- Jika produk tidak tersedia (out of stock), jangan menyusun CTA pembelian langsung;
  gunakan awareness atau waitlist.
- Jika brand knowledge tidak ditemukan pada RETRIEVED_CONTEXT, tambahkan warning.
- requires_human_review selalu true.
- Tulis claims secara verbatim sesuai fakta pada TOOL_RESULTS (mis. nama produk persis,
  harga persis). Jangan menambah kata sifat atau kualitas yang tidak ada di data
  (mis. "renyah", "berkualitas", "premium") kecuali memang tercantum di data.
- claims wajib berupa fakta pendek (frasa atau klausa tunggal), bukan kalimat utuh.
  Contoh benar: "harga Rp 25.000", "produk Keripik Pedas 100g", "lokasi Bandung",
  "stok tersedia". Contoh salah: "Nama usaha adalah Kopdes yang berlokasi di Bandung."
- claims harus memakai kata kunci yang persis ada pada TOOL_RESULTS.

Gaya penulisan (sosial media / Instagram):
- Tulis seperti admin media sosial yang hangat dan bersahabat, bukan teks formal.
  Buka dengan hook ringan yang mengajak (mis. "Halo Bandung!", "Pencinta batik,
  merapat!"), lalu lanjut dengan informasi produk yang menarik.
- Bahasa santai namun tetap sopan dan profesional untuk UMKM. Boleh sedikit emoji
  yang relevan (maksimal 2-3) untuk memberi kesan hidup, jangan berlebihan.
- Variasikan struktur kalimat: garis pembuka pendek, kalimat deskriptif singkat,
  lalu ajakan bertindak. Hindari kalimat panjang bertele-tele dan gaya berita.
- Copy tidak boleh berupa daftar fakta yang kaku ("Harga Rp X. Tersedia di Y.").
  Padukan fakta ke dalam kalimat yang mengalir dan menarik dibaca.
- DILARANG memakai em-dash (—), en-dash (–), atau tanda pisang panjang apa pun.
  Gunakan titik, koma, atau titik dua sebagai pengganti.
"""


def sanitize_block(text: str, max_chars: int = 6000) -> str:
    """Escape delimiter tokens so retrieved content cannot break block boundaries."""
    text = text.replace("<", "&lt;").replace(">", "&gt;")
    text = text.replace("\x00", "")
    return text[:max_chars]


def build_system_instruction(prompt_version: str = PROMPT_VERSION) -> str:
    return f"[prompt_version={prompt_version}]\n{SYSTEM_INSTRUCTION}"


def _block_to_prompt(block: ContextBlock, max_chars: int) -> str:
    if block.kind == "rag_chunk":
        return f"[chunk_id={block.block_id}] {sanitize_block(block.content, max_chars)}"
    return f"[tool_call_id={block.block_id}] {sanitize_block(block.content, max_chars)}"


def build_llm_request(
    *,
    brief: PromotionBrief,
    context_blocks: list[ContextBlock],
    tools: list,
    temperature: float | None,
    max_output_tokens: int | None,
    json_schema: dict | None,
    system_instruction: str | None = None,
) -> LLMRequest:
    """Assemble a sanitized LLMRequest from brief + context blocks."""
    retrieved = [b for b in context_blocks if b.kind == "rag_chunk"]
    tool_results = [b for b in context_blocks if b.kind == "tool_result"]

    parts: list[str] = []
    parts.append(f"<USER_BRIEF>\n{brief.as_prompt_block()}\n</USER_BRIEF>")
    if retrieved:
        chunks = "\n".join(_block_to_prompt(b, 4000) for b in retrieved)
        parts.append(f"<RETRIEVED_CONTEXT>\n{chunks}\n</RETRIEVED_CONTEXT>")
    if tool_results:
        results = "\n".join(_block_to_prompt(b, 4000) for b in tool_results)
        parts.append(f"<TOOL_RESULTS>\n{results}\n</TOOL_RESULTS>")

    return LLMRequest(
        system_instruction=system_instruction or build_system_instruction(),
        user_brief="\n\n".join(parts),
        context_blocks=context_blocks,
        tools=tools,
        temperature=temperature,
        max_output_tokens=max_output_tokens,
        json_schema=json_schema,
        prompt_version=PROMPT_VERSION,
    )
