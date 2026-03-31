<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * Calendar module - Remote calendar interface.
 */

/**
 * Remote calendar client.
 */
class RemoteCalendar
{
    private string $url;
    private int $timeout = 1000;

    /**
     * Create a RemoteCalendar.
     *
     * @param string $url Calendar URL
     */
    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * Submit a digest to remote calendar.
     *
     * @param int[] $digest Digest bytes
     * @return Timestamp Timestamp from calendar response
     * @throws URLError If request fails
     */
    public function submit(array $digest): Timestamp
    {
        $url = rtrim($this->url, '/') . '/digest';
        $body = pack('C*', ...$digest);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Accept: application/vnd.opentimestamps.v1\r\n" .
                    "Content-Type: application/x-www-form-urlencoded\r\n" .
                    "User-Agent: php-opentimestamps\r\n",
                'content' => $body,
                'timeout' => (int)($this->timeout / 1000)
            ]
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new URLError('Failed to submit to calendar');
        }

        if (strlen($response) > 10000) {
            throw new ExceededSizeError('Calendar response exceeded size limit');
        }

        $ctx = new StreamDeserializationContext(Utils::toBytes($response));
        return Timestamp::deserialize($ctx, $digest);
    }

    /**
     * Get timestamp for a commitment.
     *
     * @param int[] $commitment Commitment bytes
     * @return Timestamp Timestamp from calendar
     * @throws CommitmentNotFoundError If not found
     * @throws URLError If request fails
     */
    public function getTimestamp(array $commitment): Timestamp
    {
        $hex = Utils::bytesToHex($commitment);
        $url = rtrim($this->url, '/') . '/timestamp/' . $hex;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/vnd.opentimestamps.v1\r\n" .
                    "User-Agent: php-opentimestamps\r\n",
                'timeout' => (int)($this->timeout / 1000)
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new URLError('Failed to get timestamp from calendar');
        }

        if (strlen($response) > 10000) {
            throw new ExceededSizeError('Calendar response exceeded size limit');
        }

        $ctx = new StreamDeserializationContext(Utils::toBytes($response));
        return Timestamp::deserialize($ctx, $commitment);
    }
}

/**
 * URL whitelist for calendar filtering.
 */
class UrlWhitelist
{
    /** @var string[] */
    private array $urls = [];

    /**
     * @param string[]|null $urls URLs to add
     */
    public function __construct(?array $urls = null)
    {
        if ($urls !== null) {
            foreach ($urls as $url) {
                $this->add($url);
            }
        }
    }

    /**
     * Add URL to whitelist.
     *
     * @param string $url URL to add
     */
    public function add(string $url): void
    {
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $this->urls[] = 'http://' . $url;
            $this->urls[] = 'https://' . $url;
        } else {
            $this->urls[] = $url;
        }
    }

    /**
     * Check if URL is in whitelist.
     *
     * @param string $url URL to check
     * @return bool True if whitelisted
     */
    public function contains(string $url): bool
    {
        foreach ($this->urls as $whitelisted) {
            // Simple pattern matching for wildcards
            $pattern = str_replace('*', '.*', preg_quote($whitelisted, '/'));
            if (preg_match('/^' . $pattern . '$/', $url)) {
                return true;
            }
        }
        return false;
    }

    public function __toString(): string
    {
        return 'UrlWhitelist([' . implode(',', $this->urls) . '])';
    }
}

/**
 * Default calendar configuration.
 */
class Calendar
{
    /** @var UrlWhitelist Default whitelist */
    public static UrlWhitelist $DEFAULT_CALENDAR_WHITELIST;

    /** @var string[] Default aggregator URLs */
    public const DEFAULT_AGGREGATORS = [
        'https://a.pool.opentimestamps.org',
        'https://b.pool.opentimestamps.org',
        'https://a.pool.eternitywall.com',
        'https://ots.btc.catallaxy.com'
    ];

    public static function init(): void
    {
        self::$DEFAULT_CALENDAR_WHITELIST = new UrlWhitelist([
            'https://*.calendar.opentimestamps.org',
            'https://*.calendar.eternitywall.com',
            'https://*.calendar.catallaxy.com'
        ]);
    }
}

// Initialize calendar defaults
Calendar::init();
