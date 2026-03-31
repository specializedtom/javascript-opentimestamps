<?php

declare(strict_types=1);

namespace OpenTimestamps;

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
