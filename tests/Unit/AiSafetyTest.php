<?php

use App\Services\Ai\MessageBuilder;
use App\Services\Ai\SafePayloadSanitizer;

it('redacts forbidden key fragments deeply', function () {
    $sanitizer = new SafePayloadSanitizer;
    $out = $sanitizer->sanitize([
        'ok' => 1,
        'nested' => [
            'password_hash' => 'x',
            'payout_details' => 'secret bank',
            'domain' => 'safe.test',
        ],
    ]);

    expect($out['ok'])->toBe(1)
        ->and($out['nested']['password_hash'])->toBe('[redacted]')
        ->and($out['nested']['payout_details'])->toBe('[redacted]')
        ->and($out['nested']['domain'])->toBe('safe.test');
});

it('message builder strips secrets from ask context', function () {
    $builder = new MessageBuilder(new SafePayloadSanitizer);
    $ctx = $builder->outgoingContextForAsk([
        'key' => 'x',
        'title' => 't',
        'figures' => ['token' => 'abc', 'revenue' => 1],
        'rows' => [['credential_secret' => 'nope', 'domain' => 'd.test']],
        'narrative_seed' => 'seed',
    ]);

    $json = json_encode($ctx);
    expect($json)->toContain('[redacted]')
        ->and($json)->toContain('d.test')
        ->and($json)->not->toContain('nope')
        ->and($json)->not->toContain('abc');
});
