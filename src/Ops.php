<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * Cryptographic operations for timestamps.
 */

/**
 * Base class for timestamp proof operations.
 */
abstract class Op
{
    /**
     * Maximum length of an Op result.
     */
    public function maxResultLength(): int
    {
        return 4096;
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
        if (isset(Ops::$_SUBCLS_BY_TAG[$tag])) {
            $arg = $ctx->readVarbytes((new Op())->maxResultLength(), 1);
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

/**
 * Append a suffix to a message.
 */
class OpAppend extends OpBinary
{
    public function tag(): int
    {
        return 0xf0;
    }

    public function tagName(): string
    {
        return 'append';
    }

    protected function doCall(array $msg): array
    {
        return array_merge($msg, $this->arg);
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof OpAppend && Utils::arrEq($this->arg, $another->arg);
    }
}

/**
 * Prepend a prefix to a message.
 */
class OpPrepend extends OpBinary
{
    public function tag(): int
    {
        return 0xf1;
    }

    public function tagName(): string
    {
        return 'prepend';
    }

    protected function doCall(array $msg): array
    {
        return array_merge($this->arg, $msg);
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof OpPrepend && Utils::arrEq($this->arg, $another->arg);
    }
}

/**
 * Operations that act on a single message.
 */
abstract class OpUnary extends Op
{
    public static function deserializeFromTag(StreamDeserializationContext $ctx, int $tag): ?Op
    {
        if (isset(Ops::$_SUBCLS_BY_TAG[$tag])) {
            $class = Ops::$_SUBCLS_BY_TAG[$tag];
            return new $class();
        }
        return null;
    }
}

/**
 * Reverse a message.
 */
class OpReverse extends OpUnary
{
    public function tag(): int
    {
        return 0xf2;
    }

    public function tagName(): string
    {
        return 'reverse';
    }

    protected function doCall(array $msg): array
    {
        if (count($msg) === 0) {
            throw new ValueError("Can't reverse an empty message");
        }
        return array_reverse($msg);
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof OpReverse;
    }
}

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

/**
 * SHA1 cryptographic operation.
 */
class OpSHA1 extends CryptOp
{
    public function tag(): int
    {
        return 0x02;
    }

    public function tagName(): string
    {
        return 'sha1';
    }

    public function hashlibName(): string
    {
        return 'sha1';
    }

    public function digestLength(): int
    {
        return 20;
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof OpSHA1;
    }
}

/**
 * RIPEMD160 cryptographic operation.
 */
class OpRIPEMD160 extends CryptOp
{
    public function tag(): int
    {
        return 0x03;
    }

    public function tagName(): string
    {
        return 'ripemd160';
    }

    public function hashlibName(): string
    {
        return 'ripemd160';
    }

    public function digestLength(): int
    {
        return 20;
    }

    public function equals(mixed $another): bool
    {
        return $another instanceof OpRIPEMD160;
    }
}

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

/**
 * Registry for all operation classes.
 */
class Ops
{
    /** @var array<int, class-string<Op>> */
    public static array $_SUBCLS_BY_TAG = [];

    public static function init(): void
    {
        self::$_SUBCLS_BY_TAG = [
            (new OpAppend())->tag() => OpAppend::class,
            (new OpPrepend())->tag() => OpPrepend::class,
            (new OpReverse())->tag() => OpReverse::class,
            (new OpSHA1())->tag() => OpSHA1::class,
            (new OpRIPEMD160())->tag() => OpRIPEMD160::class,
            (new OpSHA256())->tag() => OpSHA256::class,
        ];
    }
}

// Initialize the registry
Ops::init();
