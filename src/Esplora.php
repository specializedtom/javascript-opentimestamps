<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * Esplora blockchain explorer client.
 */
class Esplora
{
    private const PUBLIC_ESPLORA_URL = 'https://blockstream.info/api';
    private string $url;
    private int $timeout = 1000;

    /**
     * @param array{url?: string, timeout?: int} $options
     */
    public function __construct(array $options = [])
    {
        $this->url = $options['url'] ?? self::PUBLIC_ESPLORA_URL;
        $this->timeout = $options['timeout'] ?? 1000;
    }

    public function blockhash(int $height): string
    {
        $url = $this->url . '/block-height/' . $height;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: text/plain\r\n" .
                    "User-Agent: php-opentimestamps\r\n",
                'timeout' => (int) ($this->timeout / 1000),
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false || trim($response) === '') {
            throw new URLError('Empty response from Esplora');
        }

        return trim($response);
    }

    /**
     * @return array{merkleroot: string, time: int}
     */
    public function block(string $hash): array
    {
        $url = $this->url . '/block/' . $hash;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\n" .
                    "User-Agent: php-opentimestamps\r\n",
                'timeout' => (int) ($this->timeout / 1000),
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new URLError('Failed to get block from Esplora');
        }

        $data = json_decode($response, true);

        if (!isset($data['merkle_root']) || !isset($data['timestamp'])) {
            throw new URLError('Invalid block response');
        }

        return [
            'merkleroot' => $data['merkle_root'],
            'time' => $data['timestamp'],
        ];
    }
}
