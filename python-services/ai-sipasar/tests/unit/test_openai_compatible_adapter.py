"""Unit tests: OpenAI-compatible adapter message building and turn parsing.

These tests never touch the network.
"""

from __future__ import annotations

from sipromo.application.ports.llm import (
    ContextBlock,
    LLMRequest,
    ToolDeclaration,
    ToolResultForModel,
)
from sipromo.infrastructure.ai.openai_compatible_adapter import (
    _build_messages,
    _parse_arguments,
    _parse_turn,
    _to_openai_tool,
    _tool_result_text,
)

MODEL = "gpt-5-mini"


def _request() -> LLMRequest:
    return LLMRequest(
        system_instruction="Sistem: jadilah penulis promosi.",
        user_brief="Buatkan promosi keripik pedas.",
        context_blocks=[
            ContextBlock(block_id="chunk-1", kind="rag_chunk", content="Katalog produk."),
            ContextBlock(block_id="call-9", kind="tool_result", content='{"ok": true}'),
        ],
        tools=[
            ToolDeclaration(
                name="get_products",
                description="Ambil produk",
                parameters={"type": "object", "properties": {}},
            )
        ],
        json_schema=None,
    )


def test_build_messages_order_and_markers() -> None:
    messages = _build_messages(_request())

    assert messages[0] == {"role": "system", "content": "Sistem: jadilah penulis promosi."}
    assert messages[1] == {"role": "user", "content": "Buatkan promosi keripik pedas."}
    assert messages[2]["role"] == "user"
    assert "<RETRIEVED_CONTEXT chunk_id=chunk-1>" in messages[2]["content"]
    assert messages[3]["role"] == "user"
    assert "<TOOL_RESULTS tool_call_id=call-9>" in messages[3]["content"]


def test_to_openai_tool_shape() -> None:
    tool = _to_openai_tool(_request().tools[0])

    assert tool["type"] == "function"
    assert tool["function"]["name"] == "get_products"
    assert tool["function"]["parameters"]["type"] == "object"


def test_tool_result_text_marks_call_id() -> None:
    result = ToolResultForModel(
        call_id="call-9",
        tool_name="get_products",
        ok=True,
        data={"items": [{"name": "Keripik"}]},
    )

    text = _tool_result_text(result)

    assert "<TOOL_RESULTS tool_call_id=call-9>" in text
    assert "Keripik" in text


def test_parse_arguments_handles_json_string_and_garbage() -> None:
    assert _parse_arguments('{"a": 1}') == {"a": 1}
    assert _parse_arguments({"a": 1}) == {"a": 1}
    assert _parse_arguments("not json") == {}
    assert _parse_arguments(None) == {}


def test_parse_turn_extracts_tool_calls() -> None:
    response = {
        "choices": [
            {
                "message": {
                    "role": "assistant",
                    "tool_calls": [
                        {
                            "id": "call_1",
                            "type": "function",
                            "function": {
                                "name": "get_products",
                                "arguments": '{"product_ids": ["a"]}',
                            },
                        }
                    ],
                }
            }
        ],
        "usage": {"prompt_tokens": 10, "completion_tokens": 5, "total_tokens": 15},
    }

    turn = _parse_turn(response, _request())

    assert len(turn.tool_calls) == 1
    assert turn.tool_calls[0].call_id == "call_1"
    assert turn.tool_calls[0].tool_name == "get_products"
    assert turn.tool_calls[0].arguments == {"product_ids": ["a"]}
    assert turn.usage["total_tokens"] == 15


def test_parse_turn_extracts_final_json() -> None:
    response = {"choices": [{"message": {"role": "assistant", "content": '{"title": "Promo!"}'}}]}

    turn = _parse_turn(response, _request())

    assert turn.final_output == {"title": "Promo!"}


def test_parse_turn_marks_invalid_json() -> None:
    response = {"choices": [{"message": {"role": "assistant", "content": "maaf, ini bukan json"}}]}

    turn = _parse_turn(response, _request())

    assert turn.final_output is not None
    assert "_invalid_json" in turn.final_output


def test_parse_turn_empty_choices() -> None:
    turn = _parse_turn({"choices": []}, _request())

    assert turn.final_output is None
    assert turn.tool_calls == []
