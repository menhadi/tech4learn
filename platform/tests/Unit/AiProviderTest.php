<?php

namespace Tests\Unit;

use App\Support\AiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    public function test_claude_reads_text_after_a_thinking_block(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'thinking', 'thinking' => 'internal'],
                    ['type' => 'text', 'text' => '{"results":[]}'],
                ],
            ]),
        ]);

        $result = AiProvider::generateText([
            'provider' => 'claude', 'key' => 'test-key', 'model' => 'claude-test',
        ], 'prompt', 'system', 0.1, 1000, 10, true);

        $this->assertSame('{"results":[]}', $result);
    }

    public function test_gpt_five_uses_reasoning_compatible_parameters_and_json_mode(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"results":[]}']]],
            ]),
        ]);

        AiProvider::generateText([
            'provider' => 'chatgpt', 'key' => 'test-key', 'model' => 'gpt-5',
        ], 'prompt', 'system', 0.1, 1000, 10, true);

        Http::assertSent(function ($request) {
            $data = $request->data();
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && ($data['max_completion_tokens'] ?? null) === 1000
                && ! array_key_exists('max_tokens', $data)
                && ! array_key_exists('temperature', $data)
                && ($data['response_format']['type'] ?? null) === 'json_object';
        });
    }

    public function test_deepseek_enables_json_object_response_mode(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"results":[]}']]],
            ]),
        ]);

        AiProvider::generateText([
            'provider' => 'deepseek', 'key' => 'test-key', 'model' => 'deepseek-chat',
        ], 'prompt', 'system', 0.1, 1000, 10, true);

        Http::assertSent(fn ($request) =>
            ($request->data()['response_format']['type'] ?? null) === 'json_object'
        );
    }

    public function test_openai_vision_sends_resolved_question_images(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'output' => [['content' => [['type' => 'output_text', 'text' => '{"results":[]}']]]],
            ]),
        ]);

        $result = AiProvider::generateVision([
            'provider' => 'chatgpt', 'key' => 'test-key', 'model' => 'gpt-5',
        ], 'Solve the visual question.', [[
            'label' => 'DRAFT_ID 10 question image',
            'image_url' => 'data:image/png;base64,'.base64_encode('image-bytes'),
        ]], 'Return strict JSON.', 1000, 10);

        $this->assertSame('{"results":[]}', $result);
        Http::assertSent(function ($request) {
            $content = $request->data()['input'][0]['content'] ?? [];
            return $request->url() === 'https://api.openai.com/v1/responses'
                && collect($content)->contains(fn ($part) => ($part['type'] ?? null) === 'input_image')
                && collect($content)->contains(fn ($part) =>
                    ($part['type'] ?? null) === 'input_text'
                    && str_contains((string) ($part['text'] ?? ''), 'DRAFT_ID 10')
                );
        });
    }

    public function test_deepseek_vision_uses_responses_api_with_image_input(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'output' => [['content' => [['type' => 'output_text', 'text' => '{"results":[]}']]]],
            ]),
        ]);

        $result = AiProvider::generateVision([
            'provider' => 'deepseek', 'key' => 'test-key', 'model' => 'deepseek-v4-flash-vision-exp',
        ], 'Inspect this page.', [[
            'label' => 'PDF page 1',
            'image_url' => 'data:image/png;base64,'.base64_encode('image-bytes'),
        ]], 'Return strict JSON.', 1000, 10);

        $this->assertSame('{"results":[]}', $result);
        Http::assertSent(function ($request) {
            $content = $request->data()['input'][0]['content'] ?? [];

            return $request->url() === 'https://api.deepseek.com/responses'
                && ($request->data()['model'] ?? null) === 'deepseek-v4-flash-vision-exp'
                && collect($content)->contains(fn ($part) => ($part['type'] ?? null) === 'input_image');
        });
    }

    public function test_provider_http_errors_are_reported_instead_of_becoming_empty_responses(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => ['message' => 'Unsupported request parameter.'],
            ], 400),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ChatGPT API error: Unsupported request parameter.');

        AiProvider::generateText([
            'provider' => 'chatgpt', 'key' => 'test-key', 'model' => 'gpt-4o',
        ], 'prompt');
    }
}
