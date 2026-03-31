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
     * @throws URLError If request fails
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
            throw new URLError('Empty response from Esplora');
        }

        return trim($response);
    }

    /**
     * Get block information from hash.
     *
     * @param string $hash Block hash
     * @return array Block data with merkle_root and time
     * @throws URLError If request fails
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
            throw new URLError('Failed to get block from Esplora');
        }

        $data = json_decode($response, true);

        if (!isset($data['merkle_root']) || !isset($data['timestamp'])) {
            throw new URLError('Invalid block response');
        }

        return [
            'merkleroot' => $data['merkle_root'],
            'time' => $data['timestamp']
        ];
    }
}

/**
 * Bitcoin node RPC client.
 */
class BitcoinNode
{
    private string $authString;
    private string $urlString;

    /**
     * Create a BitcoinNode.
     *
     * @param array $bitcoinConf Configuration (rpcuser, rpcpassword, rpcconnect, rpcport)
     */
    public function __construct(array $bitcoinConf)
    {
        $this->authString = base64_encode($bitcoinConf['rpcuser'] . ':' . $bitcoinConf['rpcpassword']);
        $this->urlString = 'http://' . $bitcoinConf['rpcconnect'] . ':' . $bitcoinConf['rpcport'];
    }

    /**
     * Read Bitcoin configuration file.
     *
     * @return array Configuration array
     * @throws \Exception If config not found or invalid
     */
    public static function readBitcoinConf(): array
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');
        $paths = [
            '/.bitcoin/bitcoin.conf',
            '/AppData/Roaming/Bitcoin/bitcoin.conf',
            '/Library/Application Support/Bitcoin/bitcoin.conf'
        ];

        foreach ($paths as $path) {
            $file = $home . $path;
            if (file_exists($file)) {
                $config = self::parseBitcoinConf($file);
                if (isset($config['rpcuser']) && isset($config['rpcpassword'])) {
                    if (!isset($config['rpcconnect'])) {
                        $config['rpcconnect'] = '127.0.0.1';
                    }
                    if (!isset($config['rpcport'])) {
                        $config['rpcport'] = isset($config['testnet']) && $config['testnet'] ? '18332' : '8332';
                    }
                    return $config;
                }
            }
        }

        throw new \Exception('Invalid bitcoin.conf file');
    }

    /**
     * Parse Bitcoin config file.
     *
     * @param string $file Config file path
     * @return array Parsed configuration
     */
    private static function parseBitcoinConf(string $file): array
    {
        $config = [];
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#') || $line === '') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                $config[$key] = $value === '1' ? true : ($value === '0' ? false : $value);
            }
        }

        return $config;
    }

    /**
     * Get block header by height.
     *
     * @param int $height Block height
     * @return array Block header data
     * @throws \Exception If RPC call fails
     */
    public function getBlockHeader(int $height): array
    {
        // First get block hash
        $hashResult = $this->callRPC('getblockhash', [$height]);
        $blockHash = $hashResult['result'];

        // Then get block header
        $headerResult = $this->callRPC('getblockheader', [$blockHash]);

        return [
            'merkleroot' => $headerResult['result']['merkleroot'],
            'hash' => $headerResult['result']['hash'],
            'time' => $headerResult['result']['time']
        ];
    }

    /**
     * Make RPC call to Bitcoin node.
     *
     * @param string $method RPC method
     * @param array $params Method parameters
     * @return array RPC response
     * @throws \Exception If call fails
     */
    private function callRPC(string $method, array $params): array
    {
        $payload = json_encode([
            'id' => 'php-ots',
            'method' => $method,
            'params' => $params
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Accept: application/json\r\n" .
                    "Authorization: Basic " . $this->authString . "\r\n" .
                    "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 10
            ]
        ]);

        $response = @file_get_contents($this->urlString, false, $context);

        if ($response === false) {
            throw new \Exception('RPC call failed');
        }

        $result = json_decode($response, true);

        if (isset($result['error']) && $result['error'] !== null) {
            throw new \Exception('RPC error: ' . json_encode($result['error']));
        }

        return $result;
    }
}

/**
 * Bitcoin utility class.
 */
class Bitcoin
{
    /**
     * Read Bitcoin configuration.
     *
     * @return array Configuration array
     */
    public static function readBitcoinConf(): array
    {
        return BitcoinNode::readBitcoinConf();
    }
}
