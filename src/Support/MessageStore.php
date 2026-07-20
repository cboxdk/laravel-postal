<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Support;

use Cbox\LaravelPostal\Models\PostalMessage;
use Closure;
use Illuminate\Support\Carbon;

/**
 * The single writer for the status store. Both processors go through this
 * so dedupe, upserts and timestamps cannot drift between the webhook and
 * inbound pipelines — and so the write pattern is concurrency-safe:
 *
 * - callers wrap one delivery in transaction(), so a failure after the
 *   dedupe insert rolls the dedupe row back and the redelivery retries
 *   cleanly instead of being swallowed;
 * - upsertMessage() creates rows with insertOrIgnore and mutates them under
 *   a row lock, so two workers on the same message cannot double-create or
 *   lose counter increments.
 */
class MessageStore
{
    /**
     * Run one delivery's store work + event dispatch atomically on the
     * store models' database connection. Three attempts: two workers
     * touching the same message can deadlock on the row lock, and the
     * controller has already ACKed the delivery to Postal — a deadlock
     * victim must retry here, not die into failed_jobs.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function transaction(Closure $callback): mixed
    {
        return Models::message()::query()->getConnection()->transaction($callback, 3);
    }

    /**
     * Append a delivery to the event log. False means the dedupe key was
     * already recorded — a redelivery that must not be processed again.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordEvent(
        string $server,
        ?int $postalMessageId,
        string $dedupeKey,
        string $event,
        array $payload,
        ?float $occurredAt,
    ): bool {
        $json = json_encode($payload);

        $inserted = Models::event()::query()->insertOrIgnore([
            'server' => $server,
            'postal_message_id' => $postalMessageId !== null && $postalMessageId > 0 ? $postalMessageId : null,
            'dedupe_key' => $dedupeKey,
            'event' => $event,
            'payload' => $json === false ? '{}' : $json,
            'occurred_at' => Timestamps::parse($occurredAt),
            'created_at' => Carbon::now(),
        ]);

        return $inserted > 0;
    }

    /**
     * Create-or-update the status row for one message. Non-null attributes
     * are applied, then the mutator runs with the row locked.
     *
     * @param  array<string, string|null>  $attributes
     * @param  (Closure(PostalMessage): void)|null  $mutate
     */
    public function upsertMessage(string $server, int $postalMessageId, array $attributes, ?Closure $mutate = null): void
    {
        $model = Models::message();
        $now = Carbon::now();

        $model::query()->insertOrIgnore([
            'server' => $server,
            'postal_message_id' => $postalMessageId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $message = $model::query()
            ->where('server', $server)
            ->where('postal_message_id', $postalMessageId)
            ->lockForUpdate()
            ->firstOrFail();

        foreach ($attributes as $attribute => $value) {
            if ($value !== null) {
                $message->setAttribute($attribute, $value);
            }
        }

        if ($mutate !== null) {
            $mutate($message);
        }

        $message->save();
    }

    public function occurredAt(?float $timestamp): Carbon
    {
        $parsed = Timestamps::parse($timestamp);

        return $parsed !== null ? Carbon::instance($parsed) : Carbon::now();
    }
}
