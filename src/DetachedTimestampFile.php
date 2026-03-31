<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * Detached Timestamp File - A file containing a timestamp for another file.
 */
class DetachedTimestampFile
{
    /** @var CryptOp Hash operation */
    public CryptOp $fileHashOp;

    /** @var Timestamp The timestamp */
    public Timestamp $timestamp;

    /** Header magic bytes */
    private const HEADER_MAGIC = [
        0x00, 0x4f, 0x70, 0x65, 0x6e, 0x54, 0x69, 0x6d, 0x65, 0x73, 0x74, 0x61, 0x6d, 0x70, 0x73,
        0x00, 0x00, 0x50, 0x72, 0x6f, 0x6f, 0x66, 0x00, 0xbf, 0x89, 0xe2, 0xe8, 0x84, 0xe8, 0x92, 0x94
    ];

    /** Major version */
    private const MAJOR_VERSION = 1;

    /**
     * Create a DetachedTimestampFile.
     *
     * @param CryptOp $fileHashOp Hash operation
     * @param Timestamp $timestamp Timestamp
     * @throws ValueError If invalid parameters
     */
    public function __construct(CryptOp $fileHashOp, Timestamp $timestamp)
    {
        if ($timestamp->getDigest() !== null && count($timestamp->getDigest()) !== $fileHashOp->digestLength()) {
            throw new ValueError('Timestamp message length and fileHashOp digest length differ');
        }

        $this->fileHashOp = $fileHashOp;
        $this->timestamp = $timestamp;
    }

    /**
     * Get the digest of the file that was timestamped.
     *
     * @return int[] Message bytes
     */
    public function fileDigest(): array
    {
        return $this->timestamp->msg;
    }

    /**
     * Serialize the timestamp file.
     *
     * @param StreamSerializationContext $ctx Context
     */
    public function serialize(StreamSerializationContext $ctx): void
    {
        $ctx->writeBytes(self::HEADER_MAGIC);
        $ctx->writeVaruint(self::MAJOR_VERSION);
        $this->fileHashOp->serialize($ctx);
        $ctx->writeBytes($this->timestamp->msg);
        $this->timestamp->serialize($ctx);
    }

    /**
     * Serialize to byte array.
     *
     * @return int[] Serialized bytes
     */
    public function serializeToBytes(): array
    {
        $ctx = new StreamSerializationContext();
        $this->serialize($ctx);
        return $ctx->getOutput();
    }

    /**
     * Deserialize a timestamp file.
     *
     * @param StreamDeserializationContext|array $buffer Buffer or context
     * @return DetachedTimestampFile Deserialized file
     * @throws BadMagicError If magic bytes don't match
     * @throws UnsupportedMajorVersion If version unsupported
     */
    public static function deserialize($buffer): DetachedTimestampFile
    {
        $ctx = $buffer instanceof StreamDeserializationContext
            ? $buffer
            : new StreamDeserializationContext($buffer);

        $ctx->assertMagic(self::HEADER_MAGIC);

        $major = $ctx->readVaruint();
        if ($major !== self::MAJOR_VERSION) {
            throw new UnsupportedMajorVersion("Version $major detached timestamp files are not supported");
        }

        $fileHashOp = Op::deserialize($ctx);
        if (!$fileHashOp instanceof CryptOp) {
            throw new TypeError('Expected CryptOp for file hash operation');
        }

        $fileHash = $ctx->readBytes($fileHashOp->digestLength());
        $timestamp = Timestamp::deserialize($ctx, $fileHash);

        $ctx->assertEof();

        return new DetachedTimestampFile($fileHashOp, $timestamp);
    }

    /**
     * Create from bytes (hash the buffer).
     *
     * @param CryptOp $fileHashOp Hash operation
     * @param int[]|StreamDeserializationContext $buffer Buffer to hash
     * @return DetachedTimestampFile New instance
     */
    public static function fromBytes(CryptOp $fileHashOp, $buffer): DetachedTimestampFile
    {
        $ctx = $buffer instanceof StreamDeserializationContext
            ? $buffer
            : new StreamDeserializationContext($buffer);

        $fdHash = $fileHashOp->hashFd($ctx);
        return new DetachedTimestampFile($fileHashOp, new Timestamp($fdHash));
    }

    /**
     * Create from pre-computed hash.
     *
     * @param CryptOp $fileHashOp Hash operation
     * @param int[] $fdHash Hash bytes
     * @return DetachedTimestampFile New instance
     */
    public static function fromHash(CryptOp $fileHashOp, array $fdHash): DetachedTimestampFile
    {
        return new DetachedTimestampFile($fileHashOp, new Timestamp($fdHash));
    }

    /**
     * Convert to JSON representation.
     *
     * @return array JSON-like array
     */
    public function toJson(): array
    {
        return [
            'hash' => Utils::bytesToHex($this->fileDigest()),
            'op' => $this->fileHashOp->hashlibName(),
            'timestamp' => $this->timestamp->toJson()
        ];
    }

    /**
     * Check equality with another detached timestamp file.
     *
     * @param mixed $another Another object
     * @return bool True if equal
     */
    public function equals(mixed $another): bool
    {
        if (!$another instanceof DetachedTimestampFile) {
            return false;
        }
        if (!$this->fileHashOp->equals($another->fileHashOp)) {
            return false;
        }
        if (!$this->timestamp->equals($another->timestamp)) {
            return false;
        }
        return true;
    }

    /**
     * String representation.
     *
     * @return string String representation
     */
    public function __toString(): string
    {
        return "DetachedTimestampFile\n" .
            "fileHashOp: " . $this->fileHashOp->__toString() . "\n" .
            "timestamp: " . $this->timestamp->strTree(0, 0) . "\n";
    }
}
