<?php

declare(strict_types=1);

namespace OpenTimestamps\Attestations;

use OpenTimestamps\Serialize\StreamDeserializationContext;
use OpenTimestamps\Serialize\StreamSerializationContext;
use OpenTimestamps\Utils;

/**
 * Pending attestation - commitment recorded in remote calendar.
 */
class PendingAttestation extends TimeAttestation
{
    public string $uri = '';

    public const MAX_URI_LENGTH = 1000;
    public const ALLOWED_URI_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._/:';

    public function __construct(string $uri = '')
    {
        $this->uri = $uri;
    }

    public function tag(): array
    {
        return [0x83, 0xdf, 0xe3, 0x0d, 0x2e, 0xf9, 0x0c, 0x8e];
    }

    public function maxUriLength(): int
    {
        return self::MAX_URI_LENGTH;
    }

    public function allowedUriChars(): string
    {
        return self::ALLOWED_URI_CHARS;
    }

    public static function checkUri(string $uri): bool
    {
        if (strlen($uri) > PendingAttestation::MAX_URI_LENGTH) {
            return false;
        }
        for ($i = 0; $i < strlen($uri); $i++) {
            $char = $uri[$i];
            if (strpos(PendingAttestation::ALLOWED_URI_CHARS, $char) === false) {
                return false;
            }
        }
        return true;
    }

    public static function deserialize(StreamDeserializationContext $ctxPayload): PendingAttestation
    {
        $utf8Uri = $ctxPayload->readVarbytes(PendingAttestation::MAX_URI_LENGTH);
        $decode = Utils::bytesToChars($utf8Uri);
        return new PendingAttestation($decode);
    }

    public function serializePayload(StreamSerializationContext $ctx): void
    {
        $ctx->writeVarbytes(Utils::charsToBytes($this->uri));
    }

    public function __toString(): string
    {
        return "PendingAttestation('$this->uri')";
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof PendingAttestation &&
            Utils::arrEq($this->tag(), $another->tag()) &&
            $this->uri === $another->uri;
    }
}
