<?php

namespace App\Services\Ai;

/**
 * Builds LLM message arrays. Guarantees sanitization of all dynamic context.
 */
class MessageBuilder
{
    public function __construct(
        protected SafePayloadSanitizer $sanitizer,
    ) {}

    /**
     * Messages used for ask-your-data narration. Never includes SQL instructions to execute.
     *
     * @param  array<string, mixed>  $report
     * @return array{messages: list<array{role: string, content: string}>, safe_context: array<string, mixed>}
     */
    public function buildAskMessages(string $question, array $report): array
    {
        $safeReport = $this->sanitizer->sanitize([
            'report_key' => $report['key'] ?? null,
            'title' => $report['title'] ?? null,
            'figures' => $report['figures'] ?? [],
            'rows' => array_slice($report['rows'] ?? [], 0, 40),
            'narrative_seed' => $report['narrative_seed'] ?? null,
        ]);

        $system = <<<'TXT'
You are a careful internal portfolio analytics assistant for a website portfolio ops team.
Rules:
- Only use the provided SOURCE FIGURES JSON. Never invent numbers.
- Never request or invent credentials, passwords, API keys, bank details, or vault secrets.
- Never produce SQL or code to execute. You only narrate the given figures.
- Be concise (max ~8 short paragraphs or bullet points).
- Explicitly say numbers come from application reports, not from your training data.
- If access was denied or rows empty, say so clearly.
TXT;

        $user = "Question: {$question}\n\nSOURCE FIGURES (JSON):\n".json_encode($safeReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return [
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'safe_context' => $safeReport,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{messages: list<array{role: string, content: string}>, safe_context: array<string, mixed>}
     */
    public function buildHelperMessages(string $helper, array $context): array
    {
        $safe = $this->sanitizer->sanitize($context);

        $system = match ($helper) {
            'meta' => 'Suggest one SEO meta title (≤60 chars) and one meta description (≤155 chars). Use only the provided context. Never invent credentials or sensitive data. Format:\nMeta title: ...\nMeta description: ...',
            'rejections' => 'Summarise common themes from these rejection/revision reasons for supervisors. Bullet points only. No invented items.',
            'task_brief' => 'Draft a short task brief (objective, inputs, deliverables, out of scope) from the title/notes. Never request vault credentials or bank details.',
            default => 'Help with the following context. Never include secrets.',
        };

        return [
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)],
            ],
            'safe_context' => $safe,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{messages: list<array{role: string, content: string}>, safe_context: array<string, mixed>}
     */
    public function buildMonthlySummaryMessages(string $kind, array $report): array
    {
        $safe = $this->sanitizer->sanitize($report);
        $system = 'Write a short monthly review note for human managers from the SOURCE FIGURES only. Label it as a draft for review. No secrets, no SQL.';

        return [
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => "Kind: {$kind}\n\nSOURCE FIGURES:\n".json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)],
            ],
            'safe_context' => $safe,
        ];
    }

    /**
     * Public entry for tests: build and return the context that would go out.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function outgoingContextForAsk(array $report): array
    {
        return $this->buildAskMessages('n/a', $report)['safe_context'];
    }
}
