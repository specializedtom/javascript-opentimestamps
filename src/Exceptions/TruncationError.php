<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * Raised when truncated data is encountered during deserialization.
 */
class TruncationError extends DeserializationError
{
}
