<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * Base class for timestamp proof operations.
 */
abstract class Op
{
    /**
     * Maximum length of an Op result.
     */
    public const MAX_RESULT_LENGTH = 4096;

    /**
     * Maximum length of the message an Op can be applied to.
     */
    public function maxResultLength(): int
    {
        return self::MAX_RESULT_LENGTH;
    }

    /**
     * Maximum length of the message an Op can be applied to.
     */
    public function maxMsgLength(): int
    {
        return 4096;
    }

    /**
     * Deserialize operation from a buffer.
     *
     * @param StreamDeserializationContext $ctx Context
     * @return Op|null Deserialized operation
     */
    public static function deserialize(StreamDeserializationContext $ctx): ?Op
    {
        $tag = $ctx->readBytes(1)[0];
        return self::deserializeFromTag($ctx, $tag);
    }

    /**
     * Deserialize operation from tag.
     *
     * @param StreamDeserializationContext $ctx Context
     * @param int $tag Operation tag
     * @return Op|null Deserialized operation
     */
    public static function deserializeFromTag(StreamDeserializationContext $ctx, int $tag): ?Op
    {
        Ops::ensureInitialized();
        if (isset(Ops::$_SUBCLS_BY_TAG[$tag])) {
            $class = Ops::$_SUBCLS_BY_TAG[$tag];
            return $class::deserializeFromTag($ctx, $tag);
        }
        error_log('Unknown operation tag: ' . Utils::bytesToHex([$tag]));
        return null;
    }

    /**
     * Serialize operation.
     *
     * @param StreamSerializationContext $ctx Context
     */
    public function serialize(StreamSerializationContext $ctx): void
    {
        $ctx->writeByte($this->tag());
    }

    /**
     * Apply the operation to a message.
     *
     * @param int[] $msg Message bytes
     * @return int[] Result bytes
     */
    public function call(array $msg): array
    {
        if (count($msg) > $this->maxMsgLength()) {
            throw new ValueError('Message too long');
        }
        $result = $this->doCall($msg);
        if (count($result) > $this->maxResultLength()) {
            throw new ValueError('Result too long');
        }
        return $result;
    }

    /**
     * Internal call implementation.
     *
     * @param int[] $msg Message bytes
     * @return int[] Result bytes
     */
    abstract protected function doCall(array $msg): array;

    /**
     * Get operation tag.
     *
     * @return int Tag byte
     */
    abstract public function tag(): int;

    /**
     * Get operation name.
     *
     * @return string Operation name
     */
    abstract public function tagName(): string;

    /**
     * Check equality with another operation.
     *
     * @param mixed $another Another object
     * @return bool True if equal
     */
    public function equals(mixed $another): bool
    {
        return false;
    }

    public function __toString(): string
    {
        return $this->tagName();
    }
}
