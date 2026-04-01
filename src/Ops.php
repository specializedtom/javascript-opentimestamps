<?php

declare(strict_types=1);

namespace OpenTimestamps;

use OpenTimestamps\Ops\Op;
use OpenTimestamps\Ops\OpAppend;
use OpenTimestamps\Ops\OpPrepend;
use OpenTimestamps\Ops\OpReverse;
use OpenTimestamps\Ops\OpSHA1;
use OpenTimestamps\Ops\OpRIPEMD160;
use OpenTimestamps\Ops\OpSHA256;

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
        if (!self::$_initialized) {
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
    }

    /**
     * Ensure the registry is initialized.
     */
    public static function ensureInitialized(): void
    {
        if (!self::$_initialized) {
            self::init();
        }
    }
}

// Lazy initialization - will be initialized on first use
