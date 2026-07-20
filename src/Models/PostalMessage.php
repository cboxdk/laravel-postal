<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Models;

use Cbox\LaravelPostal\Support\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * The idempotent status row for one message on one Postal server, kept
 * current by the webhook spine.
 *
 * @property int $id
 * @property string $server
 * @property int $postal_message_id
 * @property string|null $token
 * @property string|null $message_id
 * @property string|null $direction
 * @property string|null $to
 * @property string|null $from
 * @property string|null $subject
 * @property string|null $tag
 * @property string|null $spam_status
 * @property string|null $status
 * @property string|null $status_details
 * @property int $opens
 * @property int $clicks
 * @property string|null $last_event
 * @property Carbon|null $last_event_at
 */
class PostalMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'postal_message_id' => 'integer',
            'opens' => 'integer',
            'clicks' => 'integer',
            'last_event_at' => 'datetime',
        ];
    }

    /**
     * The model this message was sent for (set by the notification channel).
     *
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The processed webhook deliveries for this message. A query method, not
     * a relation: Postal message ids are only unique per server, so the
     * server scoping must never be dropped by an eager load.
     *
     * @return Builder<PostalMessageEvent>
     */
    public function webhookEvents(): Builder
    {
        return Models::event()::query()
            ->where('server', $this->server)
            ->where('postal_message_id', $this->postal_message_id);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForServer(Builder $query, string $server): Builder
    {
        return $query->where('server', $server);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', 'Sent');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBounced(Builder $query): Builder
    {
        return $query->where('status', 'Bounced');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('status', ['HardFail', 'Bounced']);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeHeld(Builder $query): Builder
    {
        return $query->where('status', 'Held');
    }
}
