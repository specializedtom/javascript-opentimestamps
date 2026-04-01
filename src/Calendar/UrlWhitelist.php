<?php

declare(strict_types=1);

namespace OpenTimestamps\Calendar;

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
