<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * RIPEMD160 cryptographic operation.
 */
class OpRIPEMD160 extends CryptOp
{
    public function tag(): int
    {
        return 0x03;
    }

    public function tagName(): string
    {
        return 'ripemd160';
    }

    public function hashlibName(): string
    {
        return 'ripemd160';
    }

    public function digestLength(): int
    {
        return 20;
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof OpRIPEMD160;
    }
}
