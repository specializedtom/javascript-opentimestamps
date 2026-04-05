<?php

declare(strict_types=1);

namespace OpenTimestamps\Attestations;

use OpenTimestamps\Exceptions\VerificationError;
use OpenTimestamps\StreamDeserializationContext;
use OpenTimestamps\StreamSerializationContext;
use OpenTimestamps\Utils;

/**
 * Bitcoin Block Header Attestation.
 */
class BitcoinBlockHeaderAttestation extends TimeAttestation
{
    public int $height = 0;

    public function __construct(int $height = 0)
    {
        $this->height = $height;
    }

    public function tag(): array
    {
        return [0x05, 0x88, 0x96, 0x0d, 0x73, 0xd7, 0x19, 0x01];
    }

    public static function deserialize(StreamDeserializationContext $ctxPayload): BitcoinBlockHeaderAttestation
    {
        $height = $ctxPayload->readVaruint();
        return new BitcoinBlockHeaderAttestation($height);
    }

    public function serializePayload(StreamSerializationContext $ctx): void
    {
        $ctx->writeVaruint($this->height);
    }

    public function __toString(): string
    {
        return 'BitcoinBlockHeaderAttestation(' . $this->height . ')';
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof BitcoinBlockHeaderAttestation &&
            Utils::arrEq($this->tag(), $another->tag()) &&
            $this->height === $another->height;
    }

    /**
     * Verify attestation against a block header.
     *
     * @param int[] $digest Digest bytes
     * @param array $block Block header data
     * @return int Block time
     * @throws VerificationError If verification fails
     */
    public function verifyAgainstBlockheader(array $digest, array $block): int
    {
        if (count($digest) !== 32) {
            throw new VerificationError('Expected digest with length 32 bytes; got ' . count($digest) . ' bytes');
        } elseif (!Utils::arrEq($digest, Utils::hexToBytes($block['merkleroot']))) {
            throw new VerificationError('Digest does not match merkleroot');
        }
        return $block['time'];
    }
}
