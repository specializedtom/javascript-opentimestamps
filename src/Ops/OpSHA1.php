<?php

declare(strict_types=1);

namespace OpenTimestamps\Ops;

/**
 * SHA1 cryptographic operation.
 */
class OpSHA1 extends CryptOp
{
    public function tag(): int
    {
        return 0x02;
    }

    public function tagName(): string
    {
        return 'sha1';
    }

    public function hashlibName(): string
    {
        return 'sha1';
    }

    public function digestLength(): int
    {
        return 20;
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof OpSHA1;
    }
}
