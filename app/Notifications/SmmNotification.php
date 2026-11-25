<?php

namespace App\Notifications;

use App\Domain\Notifications\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi umum SMM: email + lonceng di aplikasi (+ WhatsApp bila diaktifkan nanti).
 * Dikirim lewat antrian supaya halaman tidak menunggu SMTP.
 */
class SmmNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<int,string> $lines */
    public function __construct(
        public string $subject,
        public array $lines,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public string $kind = 'info',
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        if (config('smm.whatsapp.enabled')) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[SMM] '.$this->subject)
            ->greeting('Yth. '.$notifiable->name.',');
        foreach ($this->lines as $line) {
            $mail->line($line);
        }
        if ($this->actionUrl) {
            $mail->action($this->actionText ?? 'Buka SMM', $this->actionUrl);
        }

        return $mail->salutation('Salam, Sistem Manajemen Mutu PT Artha Safety Indonesia');
    }

    public function toArray(object $notifiable): array
    {
        return ['subject' => $this->subject, 'lines' => $this->lines, 'url' => $this->actionUrl, 'kind' => $this->kind];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return '*'.$this->subject."*\n".implode("\n", $this->lines).($this->actionUrl ? "\n".$this->actionUrl : '');
    }
}
