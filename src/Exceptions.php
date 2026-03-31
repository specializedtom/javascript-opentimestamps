<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * Exception classes for the OpenTimestamps library.
 */

/**
 * Base exception class for all errors.
 */
class Error extends \Exception
{
}

/**
 * Base class for value-related errors.
 */
class ValueError extends Error
{
}

/**
 * Base class for type-related errors.
 */
class TypeError extends Error
{
}

/**
 * Base class for deserialization errors.
 */
class DeserializationError extends Error
{
}

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

/**
 * Raised when a major version is unsupported.
 */
class UnsupportedMajorVersion extends Error
{
}

/**
 * Raised when truncated data is encountered during deserialization.
 */
class TruncationError extends DeserializationError
{
}

/**
 * Raised when trailing garbage is found after deserialization.
 */
class TrailingGarbageError extends DeserializationError
{
}

/**
 * Raised when data is too deeply nested to deserialize.
 */
class RecursionLimitError extends DeserializationError
{
}

/**
 * Wrong type for specified serializer.
 */
class SerializerTypeError extends TypeError
{
}

/**
 * Inappropriate value to be serialized (of correct type).
 */
class SerializerValueError extends ValueError
{
}

/**
 * Verification error for attestation failures.
 */
class VerificationError extends Error
{
}

/**
 * Commitment not found error.
 */
class CommitmentNotFoundError extends Error
{
}

/**
 * URL error for remote calendar issues.
 */
class URLError extends Error
{
}

/**
 * Exceeded size error for response limits.
 */
class ExceededSizeError extends Error
{
}
