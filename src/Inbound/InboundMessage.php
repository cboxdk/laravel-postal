<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Inbound;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * An inbound message delivered by a Postal route to an HTTP endpoint.
 * Handles both endpoint formats: "Hash" (parsed fields, optional
 * attachments) and "RawMessage" (the full RFC 2822 message, exposed via
 * rawMessage). Shapes verified against Postal 3.3.7's HTTPSender.
 */
readonly class InboundMessage
{
    /**
     * @param  list<string>  $replyTo
     * @param  list<InboundAttachment>  $attachments
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public int $id,
        public ?string $rcptTo,
        public ?string $mailFrom,
        public ?string $token,
        public ?string $subject,
        public ?string $messageId,
        public ?float $timestamp,
        public ?int $size,
        public ?string $spamStatus,
        public bool $bounce,
        public bool $receivedWithSsl,
        public ?string $to,
        public ?string $cc,
        public ?string $from,
        public ?string $date,
        public ?string $inReplyTo,
        public ?string $references,
        public ?string $autoSubmitted,
        public array $replyTo,
        public ?string $plainBody,
        public ?string $repliesFromPlainBody,
        public ?string $htmlBody,
        public ?int $attachmentQuantity,
        public array $attachments,
        private ?string $encodedMessage,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $attachments = [];

        if (is_array($payload['attachments'] ?? null)) {
            foreach ($payload['attachments'] as $attachment) {
                if (is_array($attachment)) {
                    $attachments[] = InboundAttachment::fromArray(Coerce::map($attachment));
                }
            }
        }

        $replyTo = [];

        if (is_array($payload['reply_to'] ?? null)) {
            foreach ($payload['reply_to'] as $value) {
                if (is_string($value)) {
                    $replyTo[] = $value;
                }
            }
        } elseif (is_string($payload['reply_to'] ?? null)) {
            $replyTo[] = $payload['reply_to'];
        }

        $encodedMessage = isset($payload['message']) && is_string($payload['message'])
            ? $payload['message']
            : null;

        return new self(
            id: Coerce::int($payload['id'] ?? null),
            rcptTo: Coerce::stringOrNull($payload['rcpt_to'] ?? null),
            mailFrom: Coerce::stringOrNull($payload['mail_from'] ?? null),
            token: Coerce::stringOrNull($payload['token'] ?? null),
            subject: Coerce::stringOrNull($payload['subject'] ?? null),
            messageId: Coerce::stringOrNull($payload['message_id'] ?? null),
            timestamp: Coerce::floatOrNull($payload['timestamp'] ?? null),
            size: Coerce::intOrNull($payload['size'] ?? null),
            spamStatus: Coerce::stringOrNull($payload['spam_status'] ?? null),
            bounce: Coerce::flag($payload['bounce'] ?? null),
            receivedWithSsl: Coerce::flag($payload['received_with_ssl'] ?? null),
            to: Coerce::stringOrNull($payload['to'] ?? null),
            cc: Coerce::stringOrNull($payload['cc'] ?? null),
            from: Coerce::stringOrNull($payload['from'] ?? null),
            date: Coerce::stringOrNull($payload['date'] ?? null),
            inReplyTo: Coerce::stringOrNull($payload['in_reply_to'] ?? null),
            references: Coerce::stringOrNull($payload['references'] ?? null),
            autoSubmitted: Coerce::stringOrNull($payload['auto_submitted'] ?? null),
            replyTo: $replyTo,
            plainBody: Coerce::stringOrNull($payload['plain_body'] ?? null),
            repliesFromPlainBody: Coerce::stringOrNull($payload['replies_from_plain_body'] ?? null),
            htmlBody: Coerce::stringOrNull($payload['html_body'] ?? null),
            attachmentQuantity: Coerce::intOrNull($payload['attachment_quantity'] ?? null),
            attachments: $attachments,
            encodedMessage: $encodedMessage,
            raw: $payload,
        );
    }

    /**
     * True when this delivery used the RawMessage endpoint format.
     */
    public function isRaw(): bool
    {
        return $this->encodedMessage !== null;
    }

    /**
     * The full decoded RFC 2822 source (RawMessage format only). Decoded
     * lazily — a multi-MB message costs nothing until somebody reads it.
     */
    public function rawMessage(): ?string
    {
        return $this->encodedMessage !== null ? base64_decode($this->encodedMessage) : null;
    }

    /**
     * The idempotency key for this delivery: the Postal message id, or a
     * content hash when the payload carries no usable id — so distinct
     * id-less messages never collapse onto one key.
     */
    public function dedupeKey(string $server): string
    {
        if ($this->id > 0) {
            return "{$server}:inbound:{$this->id}";
        }

        $payload = json_encode($this->raw);

        return "{$server}:inbound:".hash('sha256', $payload === false ? '' : $payload);
    }
}
