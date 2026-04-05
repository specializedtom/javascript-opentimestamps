<?php

declare(strict_types=1);

namespace OpenTimestamps\Ops;

use OpenTimestamps\Serialize\StreamDeserializationContext;
use OpenTimestamps\Serialize\StreamSerializationContext;
use OpenTimestamps\Utils;

/**
 * Operations that act on a message and a single argument.
 */
abstract class OpBinary extends Op
{
    public array $arg = [];

    public function __construct(array $arg = [])
    {
        $this->arg = $arg;
    }

    public static function deserializeFromTag(StreamDeserializationContext $ctx, int $tag): ?Op
    {
        Ops::ensureInitialized();
        if (isset(Ops::$_SUBCLS_BY_TAG[$tag])) {
            $arg = $ctx->readVarbytes(Op::MAX_RESULT_LENGTH, 1);
            $class = Ops::$_SUBCLS_BY_TAG[$tag];
            return new $class($arg);
        }
        return null;
    }

    public function serialize(StreamSerializationContext $ctx): void
    {
        parent::serialize($ctx);
        $ctx->writeVarbytes($this->arg);
    }

    public function __toString(): string
    {
        return $this->tagName() . ' ' . Utils::bytesToHex($this->arg);
    }
}
