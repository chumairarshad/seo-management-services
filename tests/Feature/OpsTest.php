<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['ops.token' => null]);
});

it('returns not found for ops when token is empty', function () {
    config(['ops.token' => '']);
    config(['ops.token' => null]);

    $this->get('/_ops/cache-clear?token=anything')
        ->assertNotFound();

    $this->get('/_ops/migrate')
        ->assertNotFound();
});

it('rejects wrong ops token', function () {
    config(['ops.token' => 'correct-secret-token-of-sufficient-length']);

    $this->get('/_ops/cache-clear?token=wrong-token')
        ->assertForbidden();

    $this->get('/_ops/cache-clear')
        ->assertForbidden();
});

it('disables the ops route entirely when the token is too short to defend', function () {
    // A guessable token must not stand in for authentication on an endpoint that
    // runs Artisan; the surface stays 404 as if it were never configured.
    config(['ops.token' => 'short-token']);

    $this->get('/_ops/cache-clear?token=short-token')->assertNotFound();
    $this->get('/_ops/migrate?token=short-token')->assertNotFound();
});

it('keeps the ops response out of caches, crawlers and referrers', function () {
    config(['ops.token' => 'correct-secret-token-of-sufficient-length']);

    $response = $this->get('/_ops/cache-clear?token=correct-secret-token-of-sufficient-length');

    $response->assertOk();
    expect($response->headers->get('referrer-policy'))->toBe('no-referrer')
        ->and($response->headers->get('cache-control'))->toContain('no-store')
        ->and($response->headers->get('x-robots-tag'))->toContain('noindex');
});

it('does not let cache-clear rewrite application layouts', function () {
    config(['ops.token' => 'correct-secret-token-of-sufficient-length']);

    $appLayout = resource_path('views/layouts/app.blade.php');
    $guestLayout = resource_path('views/layouts/guest.blade.php');
    $before = [file_get_contents($appLayout), file_get_contents($guestLayout)];

    $this->get('/_ops/cache-clear?token=correct-secret-token-of-sufficient-length')->assertOk();

    expect(file_get_contents($appLayout))->toBe($before[0])
        ->and(file_get_contents($guestLayout))->toBe($before[1]);
});

it('never boots Livewire from a third-party CDN', function () {
    // Remote JS on an authenticated page would run with access to the vault.
    foreach (['app', 'guest'] as $layout) {
        expect(file_get_contents(resource_path("views/layouts/{$layout}.blade.php")))
            ->not->toContain('cdn.jsdelivr.net')
            ->and(file_get_contents(resource_path("views/layouts/{$layout}.blade.php")))
            ->toContain('@livewireScripts');
    }
});

it('rejects unknown ops actions even with a valid token', function () {
    config(['ops.token' => 'correct-secret-token-of-sufficient-length']);

    $this->get('/_ops/destroy-all?token=correct-secret-token-of-sufficient-length')
        ->assertNotFound();
});

it('runs cache-clear with a valid token and returns plain text', function () {
    config(['ops.token' => 'correct-secret-token-of-sufficient-length']);

    $response = $this->get('/_ops/cache-clear?token=correct-secret-token-of-sufficient-length');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/plain');
    $response->assertSee('exit=', false);
});

it('runs migrate --force with a valid token in testing', function () {
    config(['ops.token' => 'correct-secret-token-of-sufficient-length']);

    // Tables already migrated by RefreshDatabase; artisan migrate should be a no-op success.
    $response = $this->get('/_ops/migrate?token=correct-secret-token-of-sufficient-length');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/plain');
    $response->assertSee('exit=0', false);
    expect(Schema::hasTable('users'))->toBeTrue();
});

it('runs storage-link ops and ensures public storage path exists', function () {
    config(['ops.token' => 'correct-secret-token-of-sufficient-length']);

    $response = $this->get('/_ops/storage-link?token=correct-secret-token-of-sufficient-length');

    $response->assertOk();
    expect(
        is_link(public_path('storage'))
        || is_dir(public_path('storage'))
        || file_exists(public_path('storage'))
    )->toBeTrue();
});

it('serves public storage files via media fallback route', function () {
    $dir = storage_path('app/public/ops-test');
    File::ensureDirectoryExists($dir);
    File::put($dir.'/hello.txt', 'public-disk-fallback');

    $response = $this->get('/media/public/ops-test/hello.txt');
    $response->assertOk();
    expect($response->streamedContent())->toBe('public-disk-fallback');

    $this->get('/media/public/../private/secret.txt')
        ->assertNotFound();
});

it('restores missing Livewire dist assets via livewire-assets ops', function () {
    config(['ops.token' => 'correct-secret-token-of-sufficient-length']);

    $dist = base_path('vendor/livewire/livewire/dist');
    $min = $dist.'/livewire.min.js';
    expect(is_file($min))->toBeTrue();

    // Simulate missing dist by renaming; ops should re-fetch from GitHub.
    $backup = $dist.'.bak-ops-test';
    if (is_dir($backup)) {
        File::deleteDirectory($backup);
    }
    rename($dist, $backup);

    try {
        $response = $this->get('/_ops/livewire-assets?token=correct-secret-token-of-sufficient-length');
        $response->assertOk();
        $response->assertSee('livewire.min.js exists=yes', false);
        expect(is_file($min) && filesize($min) > 1000)->toBeTrue();
    } finally {
        if (is_dir($dist)) {
            File::deleteDirectory($dist);
        }
        if (is_dir($backup)) {
            rename($backup, $dist);
        }
    }
});
