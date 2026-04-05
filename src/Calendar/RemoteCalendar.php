<?php

declare(strict_types=1);

namespace OpenTimestamps\Calendar;

use OpenTimestamps\Exceptions\ExceededSizeError;
use OpenTimestamps\Exceptions\URLError;
use OpenTimestamps\Serialize\StreamDeserializationContext;
use OpenTimestamps\Timestamp;
use OpenTimestamps\Utils;

/**
 * Remote calendar client.
 */
class RemoteCalendar
{
    private string $url;
    private int $timeout = 1000;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * @param int[] $digest
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
                'timeout' => (int) ($this->timeout / 1000),
            ],
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
     * @param int[] $commitment
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
                'timeout' => (int) ($this->timeout / 1000),
            ],
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
