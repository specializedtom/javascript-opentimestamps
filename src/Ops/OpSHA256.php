<?php

declare(strict_types=1);

namespace OpenTimestamps\Ops;

/**
 * SHA256 cryptographic operation.
 */
class OpSHA256 extends CryptOp
{
    public function tag(): int
    {
        return 0x08;
    }

    public function tagName(): string
    {
        return 'sha256';
    }

    public function hashlibName(): string
    {
        return 'sha256';
    }

    public function digestLength(): int
    {
        return 32;
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof OpSHA256;
    }
}
