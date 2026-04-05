<?php

declare(strict_types=1);

namespace OpenTimestamps\CLI;

use OpenTimestamps\Attestations\BitcoinBlockHeaderAttestation;
use OpenTimestamps\Attestations\LitecoinBlockHeaderAttestation;
use OpenTimestamps\Calendar\Calendar;
use OpenTimestamps\Calendar\UrlWhitelist;
use OpenTimestamps\Exceptions\BadMagicError;
use OpenTimestamps\Exceptions\DeserializationError;
use OpenTimestamps\Ops\OpRIPEMD160;
use OpenTimestamps\Ops\OpSHA1;
use OpenTimestamps\Ops\OpSHA256;
use OpenTimestamps\Timestamp\DetachedTimestampFile;
use OpenTimestamps\Timestamp\OpenTimestamps;
use OpenTimestamps\Utils\Utils;

/**
 * Command line interface for OpenTimestamps.
 */
class Cli
{
    /**
     * @param string[] $argv
     */
    public static function run(array $argv): int
    {
        array_shift($argv);
        if (isset($argv[0]) && in_array($argv[0], ['bin/ots', '/var/www/html/bin/ots', 'ots'], true)) {
            array_shift($argv);
        }
        $command = $argv[0] ?? null;

        if ($command === null || in_array($command, ['-h', '--help'], true)) {
            self::printHelp();
            return 0;
        }

        $args = array_slice($argv, 1);

        try {
            return match ($command) {
                'info', 'i' => self::runInfo($args),
                'stamp', 's' => self::runStamp($args),
                'verify', 'v' => self::runVerify($args),
                'upgrade', 'u' => self::runUpgrade($args),
                default => self::unknownCommand($command),
            };
        } catch (\Throwable $e) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            return 1;
        }
    }

    private static function unknownCommand(string $command): int
    {
        fwrite(STDERR, "Unknown command: $command" . PHP_EOL);
        self::printHelp();
        return 1;
    }

    private static function printHelp(): void
    {
        echo "OpenTimestamps CLI\n\n";
        echo "Usage:\n";
        echo "  ots info <FILE.ots>\n";
        echo "  ots stamp <FILE...> [--digest HEX] [--algorithm sha1|sha256|ripemd160] [--calendar URL] [--m N]\n";
        echo "  ots verify <FILE.ots> [--file FILE] [--digest HEX] [--algorithm sha1|sha256|ripemd160] [--ignore-bitcoin-node] [--timeout N]\n";
        echo "  ots upgrade <FILE.ots> [--calendar URL]\n";
    }

    /**
     * @param string[] $args
     */
    private static function runInfo(array $args): int
    {
        [$opts, $positional] = self::parseOptions($args, ['verbose', 'whitelist:', 'no-default-whitelist']);
        $file = $positional[0] ?? null;
        if ($file === null) {
            self::printHelp();
            return 1;
        }

        $detached = self::deserializeDetachedFromPath($file);
        $options = self::parseCommon($opts);
        echo OpenTimestamps::info($detached, (bool)($options['verbose'] ?? false));

        return 0;
    }

    /**
     * @param string[] $args
     */
    private static function runStamp(array $args): int
    {
        [$opts, $files] = self::parseOptions($args, [
            'calendar:', 'm:', 'digest:', 'algorithm:', 'verbose', 'whitelist:', 'no-default-whitelist',
        ]);

        $digest = $opts['digest'] ?? null;
        if ((empty($files) && $digest === null)) {
            self::printHelp();
            return 1;
        }

        $algorithm = strtolower($opts['algorithm'] ?? 'sha256');
        $op = match ($algorithm) {
            'sha1' => new OpSHA1(),
            'sha256' => new OpSHA256(),
            'ripemd160' => new OpRIPEMD160(),
            default => throw new \InvalidArgumentException("Unsupported algorithm: $algorithm"),
        };

        $detaches = [];
        if ($digest !== null) {
            $detaches[] = DetachedTimestampFile::fromHash($op, Utils::hexToBytes($digest));
        } else {
            foreach ($files as $file) {
                $detaches[] = DetachedTimestampFile::fromBytes($op, Utils::readFileAsBytes($file));
            }
        }

        $options = self::parseCommon($opts);
        if (isset($opts['calendar'])) {
            $options['calendars'] = (array)$opts['calendar'];
        }
        if (isset($opts['m'])) {
            $options['m'] = (int)$opts['m'];
        }

        OpenTimestamps::stamp($detaches, $options);

        foreach ($detaches as $i => $ots) {
            $otsFilename = $digest !== null ? ($digest . '.ots') : ($files[$i] . '.ots');
            if (file_exists($otsFilename)) {
                echo "The timestamp proof '$otsFilename' already exists" . PHP_EOL;
                continue;
            }
            Utils::writeBytesToFile($otsFilename, $ots->serializeToBytes());
            echo "The timestamp proof '$otsFilename' has been created!" . PHP_EOL;
        }

        return 0;
    }

    /**
     * @param string[] $args
     */
    private static function runVerify(array $args): int
    {
        [$opts, $positional] = self::parseOptions($args, [
            'file:', 'digest:', 'algorithm:', 'ignore-bitcoin-node', 'timeout:', 'verbose', 'whitelist:', 'no-default-whitelist',
        ]);

        $otsFile = $positional[0] ?? null;
        if ($otsFile === null) {
            self::printHelp();
            return 1;
        }

        $algorithm = strtolower($opts['algorithm'] ?? 'sha256');
        if (!in_array($algorithm, ['sha1', 'sha256', 'ripemd160'], true)) {
            throw new \InvalidArgumentException("Unsupported algorithm: $algorithm");
        }

        $detachedOts = self::deserializeDetachedFromPath($otsFile);

        if (isset($opts['digest'])) {
            echo "Assuming target hash is '{$opts['digest']}'" . PHP_EOL;
            $detached = DetachedTimestampFile::fromHash($detachedOts->fileHashOp, Utils::hexToBytes((string)$opts['digest']));
        } else {
            $target = $opts['file'] ?? preg_replace('/\.ots$/', '', $otsFile);
            echo "Assuming target filename is '$target'" . PHP_EOL;
            $detached = DetachedTimestampFile::fromBytes($detachedOts->fileHashOp, Utils::readFileAsBytes((string)$target));
        }

        $options = self::parseCommon($opts);
        $options['ignoreBitcoinNode'] = isset($opts['ignore-bitcoin-node']);
        if (isset($opts['timeout'])) {
            $options['timeout'] = (int)$opts['timeout'];
        }

        $results = OpenTimestamps::verify($detachedOts, $detached, $options);

        foreach ($results as $chain => $data) {
            $date = (new \DateTimeImmutable('@' . $data['timestamp']))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format('Y-m-d T');
            echo 'Success! ' . ucfirst((string)$chain) . ' block ' . $data['height'] . ' attests existence as of ' . $date . PHP_EOL;
        }

        return 0;
    }

    /**
     * @param string[] $args
     */
    private static function runUpgrade(array $args): int
    {
        [$opts, $positional] = self::parseOptions($args, ['calendar:', 'verbose', 'whitelist:', 'no-default-whitelist']);
        $otsFile = $positional[0] ?? null;
        if ($otsFile === null) {
            self::printHelp();
            return 1;
        }

        $raw = Utils::readFileAsBytes($otsFile);
        $detachedOts = self::deserializeDetached($raw, $otsFile);

        $options = self::parseCommon($opts);
        if (isset($opts['calendar'])) {
            $options['calendars'] = (array)$opts['calendar'];
        }

        $changed = OpenTimestamps::upgrade($detachedOts, $options);
        if ($changed) {
            Utils::writeBytesToFile($otsFile . '.bak', $raw);
            Utils::writeBytesToFile($otsFile, $detachedOts->serializeToBytes());
            echo "The file .bak was saved!" . PHP_EOL;
        }

        echo $detachedOts->timestamp->isTimestampComplete()
            ? "Success! Timestamp complete" . PHP_EOL
            : "Failed! Timestamp not complete" . PHP_EOL;

        return 0;
    }

    /**
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    private static function parseCommon(array $opts): array
    {
        $whitelist = isset($opts['no-default-whitelist'])
            ? new UrlWhitelist()
            : Calendar::getDefaultCalendarWhitelist();

        if (isset($opts['whitelist'])) {
            foreach ((array)$opts['whitelist'] as $url) {
                $whitelist->add((string)$url);
            }
        }

        return [
            'whitelist' => $whitelist,
            'verbose' => isset($opts['verbose']),
        ];
    }

    private static function deserializeDetachedFromPath(string $path): DetachedTimestampFile
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("File not found '$path'");
        }
        $bytes = Utils::readFileAsBytes($path);
        return self::deserializeDetached($bytes, $path);
    }

    /**
     * @param int[] $bytes
     */
    private static function deserializeDetached(array $bytes, string $path): DetachedTimestampFile
    {
        try {
            return DetachedTimestampFile::deserialize($bytes);
        } catch (BadMagicError) {
            throw new \RuntimeException("Error! $path is not a timestamp file.");
        } catch (DeserializationError) {
            throw new \RuntimeException("Invalid timestamp file $path");
        }
    }

    /**
     * @param string[] $args
     * @param string[] $specs
     * @return array{0: array<string,mixed>, 1: string[]}
     */
    private static function parseOptions(array $args, array $specs): array
    {
        $opts = [];
        $positionals = [];
        $aliases = [
            'v' => 'verbose',
            'l' => 'whitelist',
            'c' => 'calendar',
            'm' => 'm',
            'd' => 'digest',
            'a' => 'algorithm',
            'f' => 'file',
            'i' => 'ignore-bitcoin-node',
            't' => 'timeout',
            'h' => 'help',
        ];

        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if (!str_starts_with($arg, '-')) {
                $positionals[] = $arg;
                continue;
            }

            $key = ltrim($arg, '-');
            if (isset($aliases[$key])) {
                $key = $aliases[$key];
            }
            $needsValue = in_array($key . ':', $specs, true);
            if ($needsValue) {
                $val = $args[$i + 1] ?? null;
                $i++;
                if ($val === null || str_starts_with($val, '-')) {
                    throw new \InvalidArgumentException("Missing value for --$key");
                }
                if (isset($opts[$key])) {
                    $opts[$key] = array_merge((array)$opts[$key], [$val]);
                } else {
                    $opts[$key] = $val;
                }
            } else {
                $opts[$key] = true;
            }
        }

        return [$opts, $positionals];
    }
}
