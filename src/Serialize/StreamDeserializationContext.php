<?php

declare(strict_types=1);

namespace OpenTimestamps\Serialize;

use OpenTimestamps\Exceptions\BadMagicError;
use OpenTimestamps\Exceptions\DeserializationError;
use OpenTimestamps\Exceptions\TrailingGarbageError;
use OpenTimestamps\Exceptions\TypeError;
use OpenTimestamps\Utils;

/**
 * Stream deserialization context for reading binary data.
 */
class StreamDeserializationContext
{
    private array $buffer = [];
    private int $counter = 0;

    /**
     * @param string|int[]|\ArrayObject $stream
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
     * @return int[]
     */
    public function read(int $l): array
    {
        $length = min($l, count($this->buffer) - $this->counter);
        $result = array_slice($this->buffer, $this->counter, $length);
        $this->counter += $length;

        return $result;
    }

    public function readBool(): bool
    {
        $b = $this->read(1)[0];
        if ($b === 0xff) {
            return true;
        }
        if ($b === 0x00) {
            return false;
        }

        throw new DeserializationError("read_bool() expected 0xff or 0x00; got $b");
    }

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
     * @return int[]
     */
    public function readBytes(?int $expectedLength = null): array
    {
        if ($expectedLength === null) {
            $expectedLength = $this->readVaruint();
        }

        return $this->read($expectedLength);
    }

    /**
     * @return int[]
     */
    public function readVarbytes(int $maxLen, int $minLen = 0): array
    {
        $l = $this->readVaruint();
        if ($l > $maxLen) {
            throw new DeserializationError("varbytes max length exceeded; $l > $maxLen");
        }
        if ($l < $minLen) {
            throw new DeserializationError("varbytes min length not met; $l < $minLen");
        }

        return $this->read($l);
    }

    /**
     * @param int[] $expectedMagic
     */
    public function assertMagic(array $expectedMagic): void
    {
        $actualMagic = $this->read(count($expectedMagic));
        if (!Utils::arrEq($expectedMagic, $actualMagic)) {
            throw new BadMagicError($expectedMagic, $actualMagic);
        }
    }

    public function assertEof(): void
    {
        if ($this->counter < count($this->buffer)) {
            throw new TrailingGarbageError('Trailing garbage found after end of deserialized data');
        }
    }
}
