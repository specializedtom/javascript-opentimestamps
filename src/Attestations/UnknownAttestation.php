<?php

declare(strict_types=1);

namespace OpenTimestamps\Attestations;

use OpenTimestamps\Serialize\StreamDeserializationContext;
use OpenTimestamps\Serialize\StreamSerializationContext;
use OpenTimestamps\Utils;

/**
 * Placeholder for attestations that don't support specific types.
 */
class UnknownAttestation extends TimeAttestation
{
    private array $_tag;
    public array $payload = [];

    public function __construct(array $tag, array $payload)
    {
        $this->_tag = $tag;
        $this->payload = $payload;
    }

    public function tag(): array
    {
        return $this->_tag;
    }

    public function serializePayload(StreamSerializationContext $ctx): void
    {
        $ctx->writeBytes($this->payload);
    }

    public static function deserialize(StreamDeserializationContext $ctxPayload, array $tag): UnknownAttestation
    {
        $payload = $ctxPayload->readBytes(TimeAttestation::MAX_PAYLOAD_SIZE);
        return new UnknownAttestation($tag, $payload);
    }

    public function __toString(): string
    {
        return 'UnknownAttestation ' . Utils::bytesToHex($this->_tag) . ' ' . Utils::bytesToHex($this->payload);
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof UnknownAttestation &&
            Utils::arrEq($this->_tag, $another->_tag) &&
            Utils::arrEq($this->payload, $another->payload);
    }
}
