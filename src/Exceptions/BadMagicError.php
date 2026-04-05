<?php

declare(strict_types=1);

namespace OpenTimestamps\Exceptions;

use OpenTimestamps\Utils;

/**
 * Raised when file format magic number is incorrect.
 */
class BadMagicError extends DeserializationError
{
    public function __construct(array $expected, array $actual)
    {
        parent::__construct(sprintf(
            'Bad magic: expected %s, got %s',
            Utils::bytesToHex($expected),
            Utils::bytesToHex($actual)
        ));
    }
}
