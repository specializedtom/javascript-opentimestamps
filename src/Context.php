<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * Stream deserialization context for reading binary data.
 */
class StreamDeserializationContext
{
    private array $buffer = [];
    private int $counter = 0;

    /**
     * @param string|int[]|\ArrayObject $stream Input stream
     */
    public function __construct($stream)
    {
        if (is_string($stream)) {
            $this->buffer = Utils::toBytes($stream);
        } elseif (is_array($stream)) {
            $this->buffer = $stream;
        } else {
            throw new TypeError('Invalid stream type');
        }
    }

    public function getOutput(): array
    {
        return $this->buffer;
    }

    public function getCounter(): int
    {
        return $this->counter;
    }

    /**
     * Read buffer slice.
     *
     * @param int $l Length to read
     * @return array|null Byte array or null if at end
     */
    public function readBuffer(int $l): ?array
    {
        if ($this->counter >= count($this->buffer)) {
            return null;
        }
        $length = min($l, count($this->buffer) - $this->counter);
        $result = array_slice($this->buffer, $this->counter, $length);
        $this->counter += $length;
        return $result;
    }

    /**
     * Read bytes as array.
     *
     * @param int $l Length to read
     * @return int[] Byte array
     */
    public function read(int $l): array
    {
        $length = min($l, count($this->buffer) - $this->counter);
        $result = array_slice($this->buffer, $this->counter, $length);
        $this->counter += $length;
        return $result;
    }

    /**
     * Read a boolean value.
     *
     * @return bool Boolean value
     * @throws DeserializationError If invalid boolean byte
     */
    public function readBool(): bool
    {
        $b = $this->read(1)[0];
        if ($b === 0xff) {
            return true;
        } elseif ($b === 0x00) {
            return false;
        }
        throw new DeserializationError("read_bool() expected 0xff or 0x00; got $b");
    }

    /**
     * Read a variable-length unsigned integer.
     *
     * @return int Unsigned integer
     */
    public function readVaruint(): int
    {
        $value = 0;
        $shift = 0;
        do {
            $b = $this->read(1)[0];
            $value |= ($b & 0b01111111) << $shift;
            $shift += 7;
        } while ($b & 0b10000000);
        return $value;
    }

    /**
     * Read bytes with optional expected length.
     *
     * @param int|null $expectedLength Expected length or null for varbytes
     * @return int[] Byte array
     */
    public function readBytes(?int $expectedLength = null): array
    {
        if ($expectedLength === null) {
            $expectedLength = $this->readVaruint();
        }
        return $this->read($expectedLength);
    }

    /**
     * Read variable-length bytes.
     *
     * @param int $maxLen Maximum allowed length
     * @param int $minLen Minimum required length
     * @return int[] Byte array
     * @throws DeserializationError If length constraints violated
     */
    public function readVarbytes(int $maxLen, int $minLen = 0): array
    {
        $l = $this->readVaruint();
        if ($l > $maxLen) {
            throw new DeserializationError("varbytes max length exceeded; $l > $maxLen");
        } elseif ($l < $minLen) {
            throw new DeserializationError("varbytes min length not met; $l < $minLen");
        }
        return $this->read($l);
    }

    /**
     * Assert magic bytes.
     *
     * @param int[] $expectedMagic Expected magic bytes
     * @throws BadMagicError If magic bytes don't match
     */
    public function assertMagic(array $expectedMagic): void
    {
        $actualMagic = $this->read(count($expectedMagic));
        if (!Utils::arrEq($expectedMagic, $actualMagic)) {
            throw new BadMagicError($expectedMagic, $actualMagic);
        }
    }

    /**
     * Assert end of file/stream.
     *
     * @throws TrailingGarbageError If extra data remains
     */
    public function assertEof(): void
    {
        if ($this->counter < count($this->buffer)) {
            throw new TrailingGarbageError('Trailing garbage found after end of deserialized data');
        }
    }
}

/**
 * Stream serialization context for writing binary data.
 */
class StreamSerializationContext
{
    private array $buffer = [];

    public function getOutput(): array
    {
        return $this->buffer;
    }

    /**
     * Write a boolean value.
     *
     * @param bool $value Boolean to write
     * @throws TypeError If not a boolean
     */
    public function writeBool(bool $value): void
    {
        $this->writeByte($value ? 0xff : 0x00);
    }

    /**
     * Write a variable-length unsigned integer.
     *
     * @param int $value Integer to write
     */
    public function writeVaruint(int $value): void
    {
        if ($value === 0) {
            $this->writeByte(0);
        } else {
            while ($value !== 0) {
                $b = $value & 0b01111111;
                if ($value > 0b01111111) {
                    $b |= 0b10000000;
                }
                $this->writeByte($b);
                if ($value <= 0b01111111) {
                    break;
                }
                $value >>= 7;
            }
        }
    }

    /**
     * Write a single byte.
     *
     * @param int $value Byte value
     */
    public function writeByte(int $value): void
    {
        $this->buffer[] = $value & 0xff;
    }

    /**
     * Write multiple bytes.
     *
     * @param int[] $values Byte array
     */
    public function writeBytes(array $values): void
    {
        foreach ($values as $value) {
            $this->writeByte($value);
        }
    }

    /**
     * Write variable-length bytes.
     *
     * @param int[] $value Byte array
     */
    public function writeVarbytes(array $value): void
    {
        $this->writeVaruint(count($value));
        $this->writeBytes($value);
    }
}
