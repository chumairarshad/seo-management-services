<?php

namespace App\Enums;

enum CredentialType: string
{
    case Registrar = 'registrar';
    case Hosting = 'hosting';
    case NameserversDns = 'nameservers_dns';
    case Ssl = 'ssl';
    case CmsAdmin = 'cms_admin';
    case Database = 'database';
    case GoogleAnalytics = 'google_analytics';
    case GoogleSearchConsole = 'google_search_console';
    case AdSense = 'adsense';
    case Cdn = 'cdn';
    case BusinessEmail = 'business_email';
    case AdAffiliate = 'ad_affiliate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Registrar => 'Registrar',
            self::Hosting => 'Hosting',
            self::NameserversDns => 'Nameservers / DNS',
            self::Ssl => 'SSL',
            self::CmsAdmin => 'CMS admin',
            self::Database => 'Database',
            self::GoogleAnalytics => 'Google Analytics',
            self::GoogleSearchConsole => 'Google Search Console',
            self::AdSense => 'AdSense',
            self::Cdn => 'CDN',
            self::BusinessEmail => 'Business email',
            self::AdAffiliate => 'Ad / affiliate',
            self::Other => 'Other tool',
        };
    }

    public function typicallyExpires(): bool
    {
        return match ($this) {
            self::Registrar, self::Hosting, self::Ssl, self::BusinessEmail, self::Cdn => true,
            default => false,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
