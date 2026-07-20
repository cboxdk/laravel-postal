<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One processed webhook delivery. The unique dedupe_key (Postal's webhook
 * request uuid) is what makes redelivered webhooks idempotent.
 *
 * @property int $id
 * @property string $server
 * @property int|null $postal_message_id
 * @property string $dedupe_key
 * @property string $event
 * @property array<string, mixed> $payload
 * @property Carbon|null $occurred_at
 * @property Carbon|null $created_at
 */
class PostalMessageEvent extends Model
{
    public const ?string UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'postal_message_id' => 'integer',
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
