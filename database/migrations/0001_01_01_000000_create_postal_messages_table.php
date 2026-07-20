<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One idempotent status row per (server, Postal message id).
        Schema::create('postal_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('server');
            $table->unsignedBigInteger('postal_message_id');
            $table->string('token')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->string('direction')->nullable();
            $table->string('to')->nullable();
            $table->string('from')->nullable();
            $table->string('subject')->nullable();
            $table->string('tag')->nullable();
            $table->string('spam_status')->nullable();
            $table->string('status')->nullable();
            $table->text('status_details')->nullable();
            $table->unsignedInteger('opens')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->string('last_event')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->nullableMorphs('notifiable');
            $table->timestamps();

            $table->unique(['server', 'postal_message_id']);
            $table->index(['server', 'status']);
            $table->index(['server', 'tag']);
        });

        // A deduplicated log of every webhook delivery, keyed by Postal's
        // webhook request uuid so redeliveries are processed exactly once.
        Schema::create('postal_message_events', function (Blueprint $table): void {
            $table->id();
            $table->string('server');
            $table->unsignedBigInteger('postal_message_id')->nullable();
            $table->string('dedupe_key')->unique();
            $table->string('event');
            $table->json('payload');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['server', 'postal_message_id']);
            $table->index(['server', 'event']);
            // postal:tail polls "server = ? and id > cursor" — keep it an
            // index seek regardless of other servers' write volume.
            $table->index(['server', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_message_events');
        Schema::dropIfExists('postal_messages');
    }
};
