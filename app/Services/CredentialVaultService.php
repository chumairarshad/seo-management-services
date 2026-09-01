<?php

namespace App\Services;

use App\Models\Credential;
use App\Models\CredentialAccessLog;
use App\Models\User;
use Illuminate\Http\Request;

class CredentialVaultService
{
    /**
     * Reveal secret for authorized users and audit the access.
     *
     * @return array{username: ?string, secret: ?string, metadata: array<string, mixed>}
     */
    public function reveal(Credential $credential, User $user, ?Request $request = null): array
    {
        CredentialAccessLog::query()->create([
            'credential_id' => $credential->id,
            'user_id' => $user->id,
            'action' => 'reveal',
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);

        return $this->plaintext($credential);
    }

    /**
     * Decrypt without auditing, for re-rendering a secret the caller already revealed.
     *
     * Callers must re-check authorisation themselves; only the deliberate reveal
     * action in `reveal()` counts as an audited access.
     *
     * @return array{username: ?string, secret: ?string, metadata: array<string, mixed>}
     */
    public function plaintext(Credential $credential): array
    {
        return [
            'username' => $credential->username,
            'secret' => $credential->secret,
            'metadata' => $credential->metadata ?? [],
        ];
    }
}
