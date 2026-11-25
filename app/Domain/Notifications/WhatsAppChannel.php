<?php

namespace App\Domain\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Titik sambung WhatsApp (belum aktif). Nanti tinggal isi pengiriman ke penyedia WA gateway.
 */
class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }
        Log::info('WhatsApp (belum aktif) ke '.$notifiable->name.': '.$notification->toWhatsApp($notifiable));
    }
}
