<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\AiAvailability;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Ask-your-data orchestrator: map → whitelist report → narrate → cache/log.
 */
class AiAssistantService
{
    public function __construct(
        protected QuestionMapper $mapper,
        protected WhitelistReportService $reports,
        protected MessageBuilder $messages,
        protected AiHttpClient $client,
        protected AiBudgetService $budget,
        protected SafePayloadSanitizer $sanitizer,
    ) {}

    /**
     * @return array{
     *   answer: string,
     *   report_key: string|null,
     *   report_title: string|null,
     *   source_figures: array<string, mixed>,
     *   rows: list<array<string, mixed>>,
     *   cached: bool,
     *   ai_generated: true,
     *   denied: bool
     * }
     */
    public function ask(User $user, string $question): array
    {
        if (! AiAvailability::enabled()) {
            throw new RuntimeException('AI is not enabled.');
        }

        $question = trim($question);
        if ($question === '') {
            throw new RuntimeException('Enter a question.');
        }

        $key = $this->mapper->map($question);
        if ($key === null) {
            return [
                'answer' => 'I could not map that question to a supported report. Try one of: '
                    .implode('; ', array_values(QuestionMapper::labels())).'.',
                'report_key' => null,
                'report_title' => null,
                'source_figures' => ['supported_reports' => QuestionMapper::labels()],
                'rows' => [],
                'cached' => false,
                'ai_generated' => true,
                'denied' => false,
            ];
        }

        $report = $this->reports->run($key, $user);
        $denied = (bool) ($report['denied'] ?? false);

        $cachePayload = [
            'user' => $user->id,
            'key' => $key,
            'figures' => $report['figures'] ?? [],
            'q' => mb_strtolower($question),
        ];
        $cacheKey = 'ai:ask:'.sha1(json_encode($this->sanitizer->sanitize($cachePayload)));
        $ttl = AiAvailability::cacheTtl();

        if ($ttl > 0 && Cache::has($cacheKey)) {
            $this->budget->log($user, 'ask', $key, 0, 0, true, true, null, ['cache' => true]);
            /** @var array{answer: string, report_key: string|null, report_title: string|null, source_figures: array<string, mixed>, rows: list<array<string, mixed>>, cached: bool, ai_generated: true, denied: bool} $hit */
            $hit = Cache::get($cacheKey);
            $hit['cached'] = true;

            return $hit;
        }

        $figures = $this->sanitizer->sanitize([
            'figures' => $report['figures'] ?? [],
            'sample_rows' => array_slice($report['rows'] ?? [], 0, 15),
        ]);

        if ($denied) {
            $answer = (string) ($report['narrative_seed'] ?? 'Access denied for this report.');
            $out = $this->wrap($answer, $report, $figures, false, true);
            if ($ttl > 0) {
                Cache::put($cacheKey, $out, $ttl);
            }

            return $out;
        }

        $this->budget->assertCanSpend();

        $built = $this->messages->buildAskMessages($question, $report);
        try {
            $result = $this->client->chat($built['messages'], 0.2, 700);
            $this->budget->log($user, 'ask', $key, $result['prompt_tokens'], $result['completion_tokens'], false, true, null, [
                'report_key' => $key,
            ]);
            $answer = $result['text'] !== '' ? $result['text'] : (string) $report['narrative_seed'];
        } catch (RuntimeException $e) {
            $this->budget->log($user, 'ask', $key, 0, 0, false, false, $e->getMessage());
            $answer = $this->deterministicAnswer($report);
        }

        $out = $this->wrap($answer, $report, $figures, false, false);

        if ($ttl > 0) {
            Cache::put($cacheKey, $out, $ttl);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $figures
     * @return array{
     *   answer: string,
     *   report_key: string|null,
     *   report_title: string|null,
     *   source_figures: array<string, mixed>,
     *   rows: list<array<string, mixed>>,
     *   cached: bool,
     *   ai_generated: true,
     *   denied: bool
     * }
     */
    protected function wrap(string $answer, array $report, array $figures, bool $cached, bool $denied): array
    {
        return [
            'answer' => $answer,
            'report_key' => $report['key'] ?? null,
            'report_title' => $report['title'] ?? null,
            'source_figures' => $figures,
            'rows' => array_slice($report['rows'] ?? [], 0, 50),
            'cached' => $cached,
            'ai_generated' => true,
            'denied' => $denied,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function deterministicAnswer(array $report): string
    {
        $seed = (string) ($report['narrative_seed'] ?? 'Report completed.');
        $figures = json_encode($report['figures'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return $seed."\n\nKey figures:\n".$figures;
    }

    /**
     * Available report list for UI.
     *
     * @return array<string, string>
     */
    public function supportedReports(): array
    {
        return QuestionMapper::labels();
    }
}
