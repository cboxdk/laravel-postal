<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Dto;

/**
 * The per-recipient result of a send: Postal's internal message id and the
 * public token.
 */
readonly class SendRecipient
{
    public function __construct(
        public string $address,
        public int $id,
        public string $token,
    ) {}
}
