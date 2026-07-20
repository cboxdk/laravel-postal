<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Dto;

use InvalidArgumentException;

/**
 * An attachment for a structured send. Holds the raw bytes; base64 encoding
 * happens only when the API payload is rendered.
 */
readonly class Attachment
{
    public function __construct(
        public string $name,
        public string $contentType,
        public string $content,
    ) {}

    public static function fromPath(string $path, ?string $name = null, ?string $contentType = null): self
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            throw new InvalidArgumentException("Attachment file [{$path}] could not be read.");
        }

        return new self(
            name: $name ?? basename($path),
            contentType: $contentType ?? (mime_content_type($path) ?: 'application/octet-stream'),
            content: $content,
        );
    }

    /**
     * @return array{name: string, content_type: string, data: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'content_type' => $this->contentType,
            'data' => base64_encode($this->content),
        ];
    }
}
