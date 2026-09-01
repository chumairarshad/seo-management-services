<?php

namespace App\Services\Ai;

use App\Support\AiAvailability;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Swappable OpenAI / Anthropic client via Laravel HTTP (no heavy SDK).
 */
class AiHttpClient
{
    public function __construct(
        protected SafePayloadSanitizer $sanitizer,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{text: string, prompt_tokens: int, completion_tokens: int, raw_provider: string}
     */
    public function chat(array $messages, float $temperature = 0.2, int $maxTokens = 800): array
    {
        if (! AiAvailability::enabled()) {
            throw new RuntimeException('AI is not configured.');
        }

        $safeMessages = [];
        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? 'user');
            $content = (string) ($message['content'] ?? '');
            // Only inspect user/assistant payloads (system prompts mention secrets as policy text).
            if ($role !== 'system' && $this->sanitizer->containsSecretMaterial($content)) {
                throw new RuntimeException('Refusing to send a payload that may contain secrets.');
            }
            $safeMessages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return match (AiAvailability::provider()) {
            'anthropic' => $this->callAnthropic($safeMessages, $temperature, $maxTokens),
            default => $this->callOpenAi($safeMessages, $temperature, $maxTokens),
        };
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{text: string, prompt_tokens: int, completion_tokens: int, raw_provider: string}
     */
    protected function callOpenAi(array $messages, float $temperature, int $maxTokens): array
    {
        $url = config('ai.openai.base_url').'/chat/completions';

        try {
            $response = Http::timeout((int) config('ai.timeout', 45))
                ->withToken((string) config('ai.api_key'))
                ->acceptJson()
                ->post($url, [
                    'model' => AiAvailability::model(),
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('AI provider unreachable: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI error HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300));
        }

        $json = $response->json();
        $text = (string) data_get($json, 'choices.0.message.content', '');

        return [
            'text' => trim($text),
            'prompt_tokens' => (int) data_get($json, 'usage.prompt_tokens', 0),
            'completion_tokens' => (int) data_get($json, 'usage.completion_tokens', 0),
            'raw_provider' => 'openai',
        ];
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{text: string, prompt_tokens: int, completion_tokens: int, raw_provider: string}
     */
    protected function callAnthropic(array $messages, float $temperature, int $maxTokens): array
    {
        $system = '';
        $converted = [];
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                $system .= ($system === '' ? '' : "\n\n").$message['content'];

                continue;
            }
            $converted[] = [
                'role' => ($message['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user',
                'content' => $message['content'],
            ];
        }

        $url = config('ai.anthropic.base_url').'/messages';
        $payload = [
            'model' => AiAvailability::model(),
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => $converted,
        ];
        if ($system !== '') {
            $payload['system'] = $system;
        }

        try {
            $response = Http::timeout((int) config('ai.timeout', 45))
                ->withHeaders([
                    'x-api-key' => (string) config('ai.api_key'),
                    'anthropic-version' => (string) config('ai.anthropic.version'),
                ])
                ->acceptJson()
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('AI provider unreachable: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Anthropic error HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300));
        }

        $json = $response->json();
        $blocks = data_get($json, 'content', []);
        $text = '';
        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $text .= (string) ($block['text'] ?? '');
                }
            }
        }

        return [
            'text' => trim($text),
            'prompt_tokens' => (int) data_get($json, 'usage.input_tokens', 0),
            'completion_tokens' => (int) data_get($json, 'usage.output_tokens', 0),
            'raw_provider' => 'anthropic',
        ];
    }
}
