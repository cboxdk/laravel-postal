<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Inbound;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * An attachment on an inbound message. The bytes stay base64-encoded until
 * content() is called, so metadata-only consumers (the store, most
 * listeners) never pay the decode-and-copy cost for large attachments.
 */
readonly class InboundAttachment
{
    public function __construct(
        public string $filename,
        public string $contentType,
        public int $size,
        private string $encoded,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            filename: Coerce::string($data['filename'] ?? null),
            contentType: Coerce::string($data['content_type'] ?? null, 'application/octet-stream'),
            size: Coerce::int($data['size'] ?? null),
            encoded: Coerce::string($data['data'] ?? null),
        );
    }

    /**
     * The decoded attachment bytes.
     */
    public function content(): string
    {
        return base64_decode($this->encoded);
    }
}
