<?php

namespace App\Services\Ai;

/**
 * Maps plain-English questions to a fixed whitelist of report method keys.
 * Never produces SQL or executable code.
 */
class QuestionMapper
{
    /** @var array<string, list<string>> */
    public const KEYWORDS = [
        'revenue_drops' => [
            'revenue drop', 'dropped revenue', 'revenue down', 'sites dropped', 'lost revenue',
            'revenue declined', 'down vs', 'vs prior', 'month vs prior', 'revenue fall',
        ],
        'overdue_tasks' => [
            'overdue', 'late task', 'past due', 'tasks overdue', 'overdue task', 'missed deadline',
        ],
        'portfolio_summary' => [
            'portfolio summary', 'portfolio overview', 'how is the portfolio', 'portfolio status',
            'overall summary', 'business summary',
        ],
        'expense_spikes' => [
            'expense spike', 'cost spike', 'spending spike', 'expenses high', 'cost increase',
            'expense up', 'spend more', 'unusual expense',
        ],
        'attendance' => [
            'attendance', 'absent', 'absence', 'who is in', 'check-in', 'late arrival', 'present today',
        ],
        'top_sites' => [
            'top performing', 'best site', 'top site', 'most profit', 'highest profit',
            'profitable site', 'top profit',
        ],
        'open_approvals' => [
            'open approval', 'pending approval', 'awaiting approval', 'approval queue',
            'items to approve', 'stuck in approval',
        ],
        'scorecard' => [
            'scorecard', 'my performance', 'team performance', 'employee performance',
            'performance summary', 'work summary',
        ],
    ];

    /**
     * Human labels for UI + LLM narration prompts.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'revenue_drops' => 'Sites with revenue drops (this month vs prior)',
            'overdue_tasks' => 'Overdue tasks by assignee and project',
            'portfolio_summary' => 'Portfolio summary',
            'expense_spikes' => 'Expense spikes',
            'attendance' => 'Attendance / absence overview',
            'top_sites' => 'Top performing sites by profit',
            'open_approvals' => 'Open approvals count',
            'scorecard' => 'Employee scorecard summary',
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }

    public function map(string $question): ?string
    {
        $q = mb_strtolower(trim($question));
        if ($q === '') {
            return null;
        }

        $scores = [];
        foreach (self::KEYWORDS as $key => $phrases) {
            $score = 0;
            foreach ($phrases as $phrase) {
                if (str_contains($q, $phrase)) {
                    $score += mb_strlen($phrase);
                }
            }
            // light single-word boosts
            foreach (explode(' ', $q) as $word) {
                if (mb_strlen($word) < 4) {
                    continue;
                }
                if (str_contains($key, $word) || collect($phrases)->contains(fn ($p) => str_contains($p, $word))) {
                    $score += 1;
                }
            }
            if ($score > 0) {
                $scores[$key] = $score;
            }
        }

        if ($scores === []) {
            // Fallbacks for shorter questions
            if (preg_match('/\brevenue\b/', $q) && preg_match('/\b(drop|down|declin|lost|vs)\b/', $q)) {
                return 'revenue_drops';
            }
            if (preg_match('/\b(profit|pnl|p&l)\b/', $q)) {
                return 'portfolio_summary';
            }
            if (preg_match('/\b(task|overdue|deadline)\b/', $q)) {
                return 'overdue_tasks';
            }
            if (preg_match('/\bexpense|cost|spend\b/', $q)) {
                return 'expense_spikes';
            }
            if (preg_match('/\bapproval/', $q)) {
                return 'open_approvals';
            }
            if (preg_match('/\battend|absent/', $q)) {
                return 'attendance';
            }
            if (preg_match('/\bscorecard|performance\b/', $q)) {
                return 'scorecard';
            }
            if (preg_match('/\btop\b.*\b(site|project)|best\b/', $q)) {
                return 'top_sites';
            }
            if (preg_match('/\bportfolio|overview|summary\b/', $q)) {
                return 'portfolio_summary';
            }

            return null;
        }

        arsort($scores);

        return array_key_first($scores);
    }
}
