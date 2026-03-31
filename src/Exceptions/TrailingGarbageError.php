<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * Raised when trailing garbage is found after deserialization.
 */
class TrailingGarbageError extends DeserializationError
{
}
