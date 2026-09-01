<?php

namespace App\Enums;

enum TaskTemplateCategory: string
{
    case OnPageSeo = 'on_page_seo';
    case TechnicalSeo = 'technical_seo';

    public function label(): string
    {
        return match ($this) {
            self::OnPageSeo => 'On-page SEO',
            self::TechnicalSeo => 'Technical SEO',
        };
    }
}
