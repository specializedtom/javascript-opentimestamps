<?php

declare(strict_types=1);

namespace OpenTimestamps\Ops;

/**
 * Registry for all operation classes.
 */
class Ops
{
    /** @var array<int, class-string<Op>> */
    public static array $_SUBCLS_BY_TAG = [];

    private static bool $_initialized = false;

    public static function init(): void
    {
        if (self::$_initialized) {
            return;
        }

        self::$_SUBCLS_BY_TAG = [
            (new OpAppend())->tag() => OpAppend::class,
            (new OpPrepend())->tag() => OpPrepend::class,
            (new OpReverse())->tag() => OpReverse::class,
            (new OpSHA1())->tag() => OpSHA1::class,
            (new OpRIPEMD160())->tag() => OpRIPEMD160::class,
            (new OpSHA256())->tag() => OpSHA256::class,
        ];

        self::$_initialized = true;
    }

    public static function ensureInitialized(): void
    {
        if (!self::$_initialized) {
            self::init();
        }
    }
}
