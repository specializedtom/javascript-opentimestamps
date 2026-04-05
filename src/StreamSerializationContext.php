<?php

declare(strict_types=1);

namespace OpenTimestamps;

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

    public function writeBool(bool $value): void
    {
        $this->writeByte($value ? 0xff : 0x00);
    }

    public function writeVaruint(int $value): void
    {
        if ($value === 0) {
            $this->writeByte(0);
            return;
        }

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

    public function writeByte(int $value): void
    {
        $this->buffer[] = $value & 0xff;
    }

    /**
     * @param int[] $values
     */
    public function writeBytes(array $values): void
    {
        foreach ($values as $value) {
            $this->writeByte($value);
        }
    }

    /**
     * @param int[] $value
     */
    public function writeVarbytes(array $value): void
    {
        $this->writeVaruint(count($value));
        $this->writeBytes($value);
    }
}
