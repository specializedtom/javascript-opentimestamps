<?php

declare(strict_types=1);

namespace OpenTimestamps\Exceptions;

/**
 * Raised when data is too deeply nested to deserialize.
 */
class RecursionLimitError extends DeserializationError
{
}
