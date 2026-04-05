<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * Append a suffix to a message.
 */
class OpAppend extends OpBinary
{
    public function tag(): int
    {
        return 0xf0;
    }

    public function tagName(): string
    {
        return 'append';
    }

    protected function doCall(array $msg): array
    {
        return array_merge($msg, $this->arg);
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof OpAppend && Utils::arrEq($this->arg, $another->arg);
    }
}
