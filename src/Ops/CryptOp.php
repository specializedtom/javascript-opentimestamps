<?php

declare(strict_types=1);

namespace OpenTimestamps\Ops;

use OpenTimestamps\Serialize\StreamDeserializationContext;

/**
 * Cryptographic hash operations.
 */
abstract class CryptOp extends OpUnary
{
    abstract public function hashlibName(): string;

    public function digestLength(): int
    {
        return 0;
    }

    public function doCall(array $msg): array
    {
        $hash = hash($this->hashlibName(), pack('C*', ...$msg), true);
        $output = [];
        for ($i = 0; $i < strlen($hash); $i++) {
            $output[] = ord($hash[$i]);
        }
        return $output;
    }

    /**
     * Hash a file descriptor context.
     *
     * @param StreamDeserializationContext $ctx Context
     * @return int[] Hash bytes
     */
    public function hashFd(StreamDeserializationContext $ctx): array
    {
        $hasher = hash_init($this->hashlibName());
        while (($chunk = $ctx->readBuffer(1048576)) !== null && count($chunk) > 0) {
            hash_update($hasher, pack('C*', ...$chunk));
        }
        $hash = hash_final($hasher, true);
        $output = [];
        for ($i = 0; $i < strlen($hash); $i++) {
            $output[] = ord($hash[$i]);
        }
        return $output;
    }
}
