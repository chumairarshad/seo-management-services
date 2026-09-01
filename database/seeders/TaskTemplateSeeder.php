<?php

namespace Database\Seeders;

use App\Enums\TaskTemplateCategory;
use App\Models\ExpenseCategory;
use App\Models\TaskTemplate;
use Illuminate\Database\Seeder;

class TaskTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // On-page SEO
            ['key' => 'titles', 'title' => 'Page titles optimized', 'category' => TaskTemplateCategory::OnPageSeo, 'sort' => 10],
            ['key' => 'meta_descriptions', 'title' => 'Meta descriptions written', 'category' => TaskTemplateCategory::OnPageSeo, 'sort' => 20],
            ['key' => 'headings', 'title' => 'Heading structure (H1–H3) reviewed', 'category' => TaskTemplateCategory::OnPageSeo, 'sort' => 30],
            ['key' => 'internal_links', 'title' => 'Internal link structure in place', 'category' => TaskTemplateCategory::OnPageSeo, 'sort' => 40],
            ['key' => 'image_alts', 'title' => 'Image alt texts added', 'category' => TaskTemplateCategory::OnPageSeo, 'sort' => 50],
            ['key' => 'url_structure', 'title' => 'URL structure cleaned', 'category' => TaskTemplateCategory::OnPageSeo, 'sort' => 60],
            ['key' => 'schema', 'title' => 'Schema markup (where relevant)', 'category' => TaskTemplateCategory::OnPageSeo, 'sort' => 70],
            ['key' => 'core_pages', 'title' => 'Core pages live (home, about, contact, etc.)', 'category' => TaskTemplateCategory::OnPageSeo, 'sort' => 80],
            // Technical SEO
            ['key' => 'robots', 'title' => 'robots.txt configured', 'category' => TaskTemplateCategory::TechnicalSeo, 'sort' => 110],
            ['key' => 'sitemap', 'title' => 'XML sitemap submitted', 'category' => TaskTemplateCategory::TechnicalSeo, 'sort' => 120],
            ['key' => 'canonicals', 'title' => 'Canonical tags set', 'category' => TaskTemplateCategory::TechnicalSeo, 'sort' => 130],
            ['key' => 'https', 'title' => 'HTTPS enforced', 'category' => TaskTemplateCategory::TechnicalSeo, 'sort' => 140],
            ['key' => 'redirects', 'title' => 'Redirects mapped (legacy → new)', 'category' => TaskTemplateCategory::TechnicalSeo, 'sort' => 150],
            ['key' => 'indexation', 'title' => 'Indexation checked (no accidental noindex)', 'category' => TaskTemplateCategory::TechnicalSeo, 'sort' => 160],
            ['key' => 'mobile', 'title' => 'Mobile usability verified', 'category' => TaskTemplateCategory::TechnicalSeo, 'sort' => 170],
            ['key' => 'ga_gsc', 'title' => 'GA + GSC property verified', 'category' => TaskTemplateCategory::TechnicalSeo, 'sort' => 180],
            ['key' => 'soft_404s', 'title' => '404 / soft-404 review', 'category' => TaskTemplateCategory::TechnicalSeo, 'sort' => 190],
        ];

        foreach ($items as $item) {
            TaskTemplate::query()->updateOrCreate(
                ['key' => $item['key']],
                [
                    'title' => $item['title'],
                    'description' => null,
                    'category' => $item['category'],
                    'sort_order' => $item['sort'],
                    'is_active' => true,
                ],
            );
        }

        ExpenseCategory::query()->updateOrCreate(
            ['slug' => 'article_writer'],
            ['name' => 'Article writer', 'is_system' => true],
        );
        ExpenseCategory::query()->updateOrCreate(
            ['slug' => 'link_building'],
            ['name' => 'Link building', 'is_system' => true],
        );
    }
}
