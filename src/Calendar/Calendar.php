<?php

declare(strict_types=1);

namespace OpenTimestamps\Calendar;

/**
 * Default calendar configuration.
 */
class Calendar
{
    /** @var UrlWhitelist|null Default whitelist */
    private static ?UrlWhitelist $_defaultCalendarWhitelist = null;

    /** @var string[] Default aggregator URLs */
    public const DEFAULT_AGGREGATORS = [
        'https://a.pool.opentimestamps.org',
        'https://b.pool.opentimestamps.org',
        'https://a.pool.eternitywall.com',
        'https://ots.btc.catallaxy.com'
    ];

    /**
     * Get the default calendar whitelist, initializing lazily if needed.
     *
     * @return UrlWhitelist The default whitelist
     */
    public static function getDefaultCalendarWhitelist(): UrlWhitelist
    {
        if (self::$_defaultCalendarWhitelist === null) {
            self::$_defaultCalendarWhitelist = new UrlWhitelist([
                'https://*.calendar.opentimestamps.org',
                'https://*.calendar.eternitywall.com',
                'https://*.calendar.catallaxy.com'
            ]);
        }
        return self::$_defaultCalendarWhitelist;
    }

    /**
     * Initialize the default whitelist (for backward compatibility).
     */
    public static function init(): void
    {
        // Lazy initialization is now used by default
        self::getDefaultCalendarWhitelist();
    }
}
