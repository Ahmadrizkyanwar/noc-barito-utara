<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramWebhook extends Model
{
    public const ID_TIKET = 'tiket';

    public const ID_JARINGAN = 'jaringan';

    protected $fillable = [
        'id',
        'label',
        'description',
        'enabled',
        'bot_token',
        'chat_id',
    ];

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function isConfigured(): bool
    {
        return $this->enabled && $this->bot_token !== '' && $this->chat_id !== '';
    }
}
