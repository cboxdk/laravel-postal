<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Dto;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * A message lookup (/api/v1/messages/message). Sections mirror the
 * requested expansions — a section is null when its expansion was not
 * requested. The raw payload stays available for full fidelity.
 */
readonly class MessageDetails
{
    /**
     * @param  array<string, list<string>>|null  $headers  Header name → values, as Postal stores them.
     * @param  list<MessageAttachment>|null  $attachments
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public int $id,
        public string $token,
        public ?MessageStatus $status,
        public ?MessageMeta $details,
        public ?MessageInspection $inspection,
        public ?string $plainBody,
        public ?string $htmlBody,
        public ?array $headers,
        public ?array $attachments,
        public ?MessageActivity $activity,
        public ?string $rawMessage,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $status = Coerce::mapOrNull($data['status'] ?? null);
        $details = Coerce::mapOrNull($data['details'] ?? null);
        $inspection = Coerce::mapOrNull($data['inspection'] ?? null);
        $activity = Coerce::mapOrNull($data['activity_entries'] ?? null);

        $attachments = null;

        if (is_array($data['attachments'] ?? null)) {
            $attachments = [];

            foreach ($data['attachments'] as $attachment) {
                if (is_array($attachment)) {
                    $attachments[] = MessageAttachment::fromArray(Coerce::map($attachment));
                }
            }
        }

        $rawMessage = null;

        if (isset($data['raw_message']) && is_string($data['raw_message'])) {
            $rawMessage = base64_decode($data['raw_message']);
        }

        return new self(
            id: Coerce::int($data['id'] ?? null),
            token: Coerce::string($data['token'] ?? null),
            status: $status !== null ? MessageStatus::fromArray($status) : null,
            details: $details !== null ? MessageMeta::fromArray($details) : null,
            inspection: $inspection !== null ? MessageInspection::fromArray($inspection) : null,
            plainBody: Coerce::stringOrNull($data['plain_body'] ?? null),
            htmlBody: Coerce::stringOrNull($data['html_body'] ?? null),
            headers: self::headers($data['headers'] ?? null),
            attachments: $attachments,
            activity: $activity !== null ? MessageActivity::fromArray($activity) : null,
            rawMessage: $rawMessage,
            raw: $data,
        );
    }

    /**
     * Normalize the headers expansion: Postal stores each header as a list
     * of values; single string values are wrapped for a uniform shape.
     *
     * @return array<string, list<string>>|null
     */
    private static function headers(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $headers = [];

        foreach ($value as $name => $values) {
            $list = [];

            if (is_string($values)) {
                $list[] = $values;
            } elseif (is_array($values)) {
                foreach ($values as $entry) {
                    if (is_string($entry)) {
                        $list[] = $entry;
                    }
                }
            }

            $headers[(string) $name] = $list;
        }

        return $headers;
    }
}
