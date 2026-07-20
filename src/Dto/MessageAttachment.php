<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Dto;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * An attachment returned by the `attachments` expansion of a message
 * lookup. Content is the decoded bytes; hash is Postal's SHA1 of them.
 */
readonly class MessageAttachment
{
    public function __construct(
        public string $filename,
        public string $contentType,
        public int $size,
        public ?string $hash,
        public string $content,
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
            hash: Coerce::stringOrNull($data['hash'] ?? null),
            content: base64_decode(Coerce::string($data['data'] ?? null)),
        );
    }
}
