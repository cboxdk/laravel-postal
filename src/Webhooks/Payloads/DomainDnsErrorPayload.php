<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks\Payloads;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * The DomainDNSError payload: the outcome of Postal's automatic SPF / DKIM /
 * MX / return-path checks for a sending domain.
 */
readonly class DomainDnsErrorPayload
{
    public function __construct(
        public ServerInfo $server,
        public ?string $domain,
        public ?string $uuid,
        public ?float $dnsCheckedAt,
        public ?string $spfStatus,
        public ?string $spfError,
        public ?string $dkimStatus,
        public ?string $dkimError,
        public ?string $mxStatus,
        public ?string $mxError,
        public ?string $returnPathStatus,
        public ?string $returnPathError,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            server: ServerInfo::fromArray(Coerce::map($payload['server'] ?? null)),
            domain: Coerce::stringOrNull($payload['domain'] ?? null),
            uuid: Coerce::stringOrNull($payload['uuid'] ?? null),
            dnsCheckedAt: Coerce::floatOrNull($payload['dns_checked_at'] ?? null),
            spfStatus: Coerce::stringOrNull($payload['spf_status'] ?? null),
            spfError: Coerce::stringOrNull($payload['spf_error'] ?? null),
            dkimStatus: Coerce::stringOrNull($payload['dkim_status'] ?? null),
            dkimError: Coerce::stringOrNull($payload['dkim_error'] ?? null),
            mxStatus: Coerce::stringOrNull($payload['mx_status'] ?? null),
            mxError: Coerce::stringOrNull($payload['mx_error'] ?? null),
            returnPathStatus: Coerce::stringOrNull($payload['return_path_status'] ?? null),
            returnPathError: Coerce::stringOrNull($payload['return_path_error'] ?? null),
        );
    }
}
