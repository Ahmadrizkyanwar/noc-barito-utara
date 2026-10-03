<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Email verifikasi berbahasa Indonesia (URL & hash identik bawaan Laravel —
 * route `verification.verify` dengan `hash = sha1(email)`).
 */
class VerifyEmailIndo extends VerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifikasi Email — NOC Kabupaten Barito Utara')
            ->line('Klik tombol di bawah untuk memverifikasi alamat email Anda.')
            ->action('Verifikasi Email', $url)
            ->line('Tautan berlaku 60 menit. Jika Anda tidak merasa mendaftar, abaikan email ini.');
    }
}
