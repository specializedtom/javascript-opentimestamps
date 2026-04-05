<?php

declare(strict_types=1);

namespace OpenTimestamps\Ops;

use OpenTimestamps\StreamDeserializationContext;

/**
 * Operations that act on a single message.
 */
abstract class OpUnary extends Op
{
    public static function deserializeFromTag(StreamDeserializationContext $ctx, int $tag): ?Op
    {
        Ops::ensureInitialized();
        if (isset(Ops::$_SUBCLS_BY_TAG[$tag])) {
            $class = Ops::$_SUBCLS_BY_TAG[$tag];
            return new $class();
        }
        return null;
    }
}
