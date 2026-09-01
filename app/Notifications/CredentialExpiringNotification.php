<?php

namespace App\Notifications;

use App\Models\Credential;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class CredentialExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Credential>  $credentials
     * @param  array<int, int>  $thresholds
     */
    public function __construct(
        public Collection $credentials,
        public array $thresholds,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Mail (array driver in tests); no database channel — Hostinger-safe, no extra table.
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Credentials expiring soon')
            ->line('The following credentials are approaching expiry (thresholds: '.implode('/', $this->thresholds).' days):');

        foreach ($this->credentials as $credential) {
            $mail->line(sprintf(
                '• %s (%s) — %s — expires %s (%s days)',
                $credential->label,
                $credential->project?->domain ?? 'n/a',
                $credential->type->label(),
                $credential->expires_on?->toDateString() ?? 'n/a',
                $credential->daysUntilExpiry() ?? 'n/a',
            ));
        }

        $mail->action('Open dashboard', url('/'));

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'credential_expiry',
            'count' => $this->credentials->count(),
            'credential_ids' => $this->credentials->pluck('id')->all(),
            'thresholds' => $this->thresholds,
        ];
    }
}
