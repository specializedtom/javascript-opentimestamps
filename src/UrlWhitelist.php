<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * URL whitelist for calendar filtering.
 */
class UrlWhitelist
{
    /** @var string[] */
    private array $urls = [];

    /**
     * @param string[]|null $urls
     */
    public function __construct(?array $urls = null)
    {
        if ($urls !== null) {
            foreach ($urls as $url) {
                $this->add($url);
            }
        }
    }

    public function add(string $url): void
    {
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $this->urls[] = 'http://' . $url;
            $this->urls[] = 'https://' . $url;
            return;
        }

        $this->urls[] = $url;
    }

    public function contains(string $url): bool
    {
        foreach ($this->urls as $whitelisted) {
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
