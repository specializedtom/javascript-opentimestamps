<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * Bitcoin node RPC client.
 */
class BitcoinNode
{
    private string $authString;
    private string $urlString;

    /**
     * @param array{rpcuser: string, rpcpassword: string, rpcconnect: string, rpcport: string} $bitcoinConf
     */
    public function __construct(array $bitcoinConf)
    {
        $this->authString = base64_encode($bitcoinConf['rpcuser'] . ':' . $bitcoinConf['rpcpassword']);
        $this->urlString = 'http://' . $bitcoinConf['rpcconnect'] . ':' . $bitcoinConf['rpcport'];
    }

    /**
     * @return array<string, bool|string>
     */
    public static function readBitcoinConf(): array
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');
        $paths = [
            '/.bitcoin/bitcoin.conf',
            '/AppData/Roaming/Bitcoin/bitcoin.conf',
            '/Library/Application Support/Bitcoin/bitcoin.conf',
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
     * @return array<string, bool|string>
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
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);
            $config[$key] = $value === '1' ? true : ($value === '0' ? false : $value);
        }

        return $config;
    }

    /**
     * @return array{merkleroot: string, hash: string, time: int}
     */
    public function getBlockHeader(int $height): array
    {
        $hashResult = $this->callRPC('getblockhash', [$height]);
        $blockHash = $hashResult['result'];

        $headerResult = $this->callRPC('getblockheader', [$blockHash]);

        return [
            'merkleroot' => $headerResult['result']['merkleroot'],
            'hash' => $headerResult['result']['hash'],
            'time' => $headerResult['result']['time'],
        ];
    }

    /**
     * @param array<int, int|string> $params
     * @return array<string, mixed>
     */
    private function callRPC(string $method, array $params): array
    {
        $payload = json_encode([
            'id' => 'php-ots',
            'method' => $method,
            'params' => $params,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Accept: application/json\r\n" .
                    'Authorization: Basic ' . $this->authString . "\r\n" .
                    "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 10,
            ],
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
