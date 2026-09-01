<?php

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Services\CsvExportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\SecurityHelpers;

uses(RefreshDatabase::class);

function csvBody(string $filename, array $headers, array $rows): string
{
    $response = app(CsvExportService::class)->download($filename, $headers, $rows);

    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('neutralises spreadsheet formulas in exported CSV cells', function () {
    $body = csvBody('export.csv', ['id', 'description'], [
        [1, '=cmd|\' /C calc\'!A0'],
        [2, '+1+1'],
        [3, '@SUM(A1:A2)'],
        [4, '-500.00'],
        [5, 'Ordinary description'],
    ]);

    // Formula triggers are quoted out so Excel and Sheets treat them as text.
    expect($body)->toContain("'=cmd")
        ->and($body)->toContain("'+1+1")
        ->and($body)->toContain("'@SUM");

    // A negative amount stays a number, so finance CSVs still import as numbers.
    expect($body)->toContain('-500.00')
        ->and($body)->not->toContain("'-500.00")
        ->and($body)->toContain('Ordinary description');
});

it('escapes a formula typed into a stored description on export', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = SecurityHelpers::user('admin');

    $article = Article::query()->create([
        'project_id' => SecurityHelpers::project($admin, ['domain' => 'csv.test'])->id,
        'title' => '=HYPERLINK("http://evil.test","click")',
        'target_keyword' => 'csv injection keyword',
        'status' => ArticleStatus::Brief,
        'cost_paisa' => 0,
        'created_by' => $admin->id,
    ]);

    expect(csvBody('articles.csv', ['id', 'title'], [[$article->id, $article->title]]))
        ->toContain("'=HYPERLINK");
});
