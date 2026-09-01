<?php

namespace App\Services\Ai;

/**
 * Strip credentials, bank details, secrets, and known sensitive keys before any
 * payload is logged or sent to an LLM provider.
 */
class SafePayloadSanitizer
{
    /** @var list<string> */
    public const FORBIDDEN_KEY_FRAGMENTS = [
        'password',
        'secret',
        'api_key',
        'apikey',
        'token',
        'credential',
        'vault',
        'bank',
        'iban',
        'account_number',
        'routing',
        'payout_details',
        'payout_method',
        'two_factor',
        'private_key',
        'access_key',
        'auth_code',
        'otp',
        'pin',
        'cvv',
        'card_number',
        'ssn',
        'username_secret',
    ];

    /**
     * Deep-sanitize arrays/objects for LLM or usage logs.
     */
    public function sanitize(mixed $payload): mixed
    {
        if (is_array($payload)) {
            $out = [];
            foreach ($payload as $key => $value) {
                $keyStr = is_string($key) ? $key : (string) $key;
                if ($this->isForbiddenKey($keyStr)) {
                    $out[$key] = '[redacted]';

                    continue;
                }
                $out[$key] = $this->sanitize($value);
            }

            return $out;
        }

        if (is_string($payload)) {
            return $this->scrubString($payload);
        }

        if (is_object($payload)) {
            return $this->sanitize((array) $payload);
        }

        return $payload;
    }

    public function isForbiddenKey(string $key): bool
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]/', '', $key) ?? $key);

        foreach (self::FORBIDDEN_KEY_FRAGMENTS as $fragment) {
            $frag = strtolower(preg_replace('/[^a-z0-9]/', '', $fragment) ?? $fragment);
            if ($frag !== '' && str_contains($normalized, $frag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect leftover secret-looking values in a built message (not instructional text).
     */
    public function containsSecretMaterial(string $text): bool
    {
        // Live API key / token shapes
        if (preg_match('/\b(sk-|rk-|pk_|AKIA)[A-Za-z0-9_\-]{16,}\b/', $text)) {
            return true;
        }

        // Assigned secret values in JSON/payload form
        if (preg_match('/"(password|secret|api_key|apikey|payout_details|bank_account|iban|private_key)"\s*:\s*"(?!\[redacted\])[^"]{2,}"/i', $text)) {
            return true;
        }

        if (preg_match('/\b(password|secret|api_key|payout_details)\s*=\s*(?!\[redacted\])\S{4,}/i', $text)) {
            return true;
        }

        return false;
    }

    protected function scrubString(string $value): string
    {
        // Never ship very long opaque secrets
        if (preg_match('/^(sk-|rk-|pk_|AKIA)[A-Za-z0-9_\-]{16,}$/', trim($value))) {
            return '[redacted-token]';
        }

        return $value;
    }
}
