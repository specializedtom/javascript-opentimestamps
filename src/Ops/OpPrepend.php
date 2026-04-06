<?php

declare(strict_types=1);

namespace OpenTimestamps\Ops;

use OpenTimestamps\Utils\Utils;

/**
 * Prepend a prefix to a message.
 */
class OpPrepend extends OpBinary
{
    public function tag(): int
    {
        return 0xf1;
    }

    public function tagName(): string
    {
        return 'prepend';
    }

    protected function doCall(array $msg): array
    {
        return array_merge($this->arg, $msg);
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof OpPrepend && Utils::arrEq($this->arg, $another->arg);
    }
}
