<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Notifikasi in-app (database channel) — satu kelas untuk semua event,
 * dibedakan lewat `kind` (register|vps_request|vps_status|vps_credentials).
 *
 * data = {kind, title, body, link}
 */
class Notice extends Notification
{
    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $link,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'link' => $this->link,
        ];
    }
}
