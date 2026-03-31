<?php

declare(strict_types=1);

namespace OpenTimestamps\Esplora;

/**
 * Esplora blockchain explorer client.
 */
class Esplora
{
    private const PUBLIC_ESPLORA_URL = 'https://blockstream.info/api';
    private string $url;
    private int $timeout = 1000;

    /**
     * Create an Esplora client.
     *
     * @param array $options Options (url, timeout)
     */
    public function __construct(array $options = [])
    {
        $this->url = $options['url'] ?? self::PUBLIC_ESPLORA_URL;
        $this->timeout = $options['timeout'] ?? 1000;
    }

    /**
     * Get block hash from height.
     *
     * @param int $height Block height
     * @return string Block hash
     * @throws \OpenTimestamps\URLError If request fails
     */
    public function blockhash(int $height): string
    {
        $url = $this->url . '/block-height/' . $height;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: text/plain\r\n" .
                    "User-Agent: php-opentimestamps\r\n",
                'timeout' => (int)($this->timeout / 1000)
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false || trim($response) === '') {
            throw new \OpenTimestamps\URLError('Empty response from Esplora');
        }

        return trim($response);
    }

    /**
     * Get block information from hash.
     *
     * @param string $hash Block hash
     * @return array Block data with merkle_root and time
     * @throws \OpenTimestamps\URLError If request fails
     */
    public function block(string $hash): array
    {
        $url = $this->url . '/block/' . $hash;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\n" .
                    "User-Agent: php-opentimestamps\r\n",
                'timeout' => (int)($this->timeout / 1000)
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \OpenTimestamps\URLError('Failed to get block from Esplora');
        }

        $data = json_decode($response, true);

        if (!isset($data['merkle_root']) || !isset($data['timestamp'])) {
            throw new \OpenTimestamps\URLError('Invalid block response');
        }

        return [
            'merkleroot' => $data['merkle_root'],
            'time' => $data['timestamp']
        ];
    }
}
