<?php

declare(strict_types=1);

namespace OpenTimestamps\Ops;

use OpenTimestamps\Exceptions\ValueError;

/**
 * Reverse a message.
 */
class OpReverse extends OpUnary
{
    public function tag(): int
    {
        return 0xf2;
    }

    public function tagName(): string
    {
        return 'reverse';
    }

    protected function doCall(array $msg): array
    {
        if (count($msg) === 0) {
            throw new ValueError("Can't reverse an empty message");
        }
        return array_reverse($msg);
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof OpReverse;
    }
}
