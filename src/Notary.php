<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * Notary module - Time attestation classes.
 */

/**
 * Base class for timestamp signature verification.
 */
abstract class TimeAttestation
{
    public const MAX_PAYLOAD_SIZE = 8192;

    public function tagSize(): int
    {
        return 8;
    }

    public function maxPayloadSize(): int
    {
        return self::MAX_PAYLOAD_SIZE;
    }

    /**
     * Get the tag bytes for PendingAttestation.
     *
     * @return int[] Tag bytes
     */
    public static function pendingAttestationTag(): array
    {
        return [0x83, 0xdf, 0xe3, 0x0d, 0x2e, 0xf9, 0x0c, 0x8e];
    }

    /**
     * Get the tag bytes for BitcoinBlockHeaderAttestation.
     *
     * @return int[] Tag bytes
     */
    public static function bitcoinBlockHeaderAttestationTag(): array
    {
        return [0x05, 0x88, 0x96, 0x0d, 0x73, 0xd7, 0x19, 0x01];
    }

    /**
     * Get the tag bytes for LitecoinBlockHeaderAttestation.
     *
     * @return int[] Tag bytes
     */
    public static function litecoinBlockHeaderAttestationTag(): array
    {
        return [0x06, 0x86, 0x9a, 0x0d, 0x73, 0xd7, 0x1b, 0x45];
    }

    /**
     * Deserialize a general Time Attestation to the specific subclass.
     *
     * @param StreamDeserializationContext $ctx Context
     * @return TimeAttestation Deserialized attestation
     */
    public static function deserialize(StreamDeserializationContext $ctx): TimeAttestation
    {
        $tag = $ctx->readBytes(8);

        $serializedAttestation = $ctx->readVarbytes(TimeAttestation::MAX_PAYLOAD_SIZE);

        $ctxPayload = new StreamDeserializationContext($serializedAttestation);

        if (Utils::arrEq($tag, TimeAttestation::pendingAttestationTag())) {
            return PendingAttestation::deserialize($ctxPayload);
        } elseif (Utils::arrEq($tag, TimeAttestation::bitcoinBlockHeaderAttestationTag())) {
            return BitcoinBlockHeaderAttestation::deserialize($ctxPayload);
        } elseif (Utils::arrEq($tag, TimeAttestation::litecoinBlockHeaderAttestationTag())) {
            return LitecoinBlockHeaderAttestation::deserialize($ctxPayload);
        }

        return UnknownAttestation::deserialize($ctxPayload, $tag);
    }

    /**
     * Serialize the attestation.
     *
     * @param StreamSerializationContext $ctx Context
     */
    public function serialize(StreamSerializationContext $ctx): void
    {
        $ctx->writeBytes($this->tag());
        $ctxPayload = new StreamSerializationContext();
        $this->serializePayload($ctxPayload);
        $ctx->writeVarbytes($ctxPayload->getOutput());
    }

    /**
     * Serialize payload only.
     *
     * @param StreamSerializationContext $ctx Context
     */
    abstract public function serializePayload(StreamSerializationContext $ctx): void;

    /**
     * Get tag bytes.
     *
     * @return int[] Tag bytes
     */
    abstract public function tag(): array;

    /**
     * Compare to another attestation.
     *
     * @param TimeAttestation $other Other attestation
     * @return int Comparison result
     */
    public function compareTo(TimeAttestation $other): int
    {
        $deltaTag = Utils::arrCompare($this->tag(), $other->tag());
        if ($deltaTag === 0) {
            if ($this instanceof PendingAttestation && $other instanceof PendingAttestation) {
                return Utils::arrCompare(Utils::charsToBytes($this->uri), Utils::charsToBytes($other->uri));
            }
        }
        return $deltaTag;
    }

    /**
     * Check equality with another attestation.
     *
     * @param mixed $another Another object
     * @return bool True if equal
     */
    abstract public function equals(mixed $another): bool;

    abstract public function __toString(): string;
}

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

/**
 * Litecoin Block Header Attestation.
 */
class LitecoinBlockHeaderAttestation extends TimeAttestation
{
    public int $height = 0;

    public function __construct(int $height = 0)
    {
        $this->height = $height;
    }

    public function tag(): array
    {
        return [0x06, 0x86, 0x9a, 0x0d, 0x73, 0xd7, 0x1b, 0x45];
    }

    public static function deserialize(StreamDeserializationContext $ctxPayload): LitecoinBlockHeaderAttestation
    {
        $height = $ctxPayload->readVaruint();
        return new LitecoinBlockHeaderAttestation($height);
    }

    public function serializePayload(StreamSerializationContext $ctx): void
    {
        $ctx->writeVaruint($this->height);
    }

    public function __toString(): string
    {
        return 'LitecoinBlockHeaderAttestation(' . $this->height . ')';
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof LitecoinBlockHeaderAttestation &&
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
