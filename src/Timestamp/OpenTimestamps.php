<?php

declare(strict_types=1);

namespace OpenTimestamps\Timestamp;

use OpenTimestamps\Attestations\BitcoinBlockHeaderAttestation;
use OpenTimestamps\Attestations\LitecoinBlockHeaderAttestation;
use OpenTimestamps\Attestations\PendingAttestation;
use OpenTimestamps\Attestations\TimeAttestation;
use OpenTimestamps\Attestations\UnknownAttestation;
use OpenTimestamps\Calendar\Calendar;
use OpenTimestamps\Calendar\RemoteCalendar;
use OpenTimestamps\Bitcoin\BitcoinNode;
use OpenTimestamps\Esplora\Esplora;
use OpenTimestamps\Exceptions\VerificationError;
use OpenTimestamps\Merkle\Merkle;
use OpenTimestamps\Ops\OpAppend;
use OpenTimestamps\Ops\OpSHA256;
use OpenTimestamps\Serialize\StreamDeserializationContext;
use OpenTimestamps\Utils\Utils;

/**
 * Main OpenTimestamps class - facade for timestamp operations.
 */
class OpenTimestamps
{
    /**
     * Show information on a timestamp.
     *
     * @param DetachedTimestampFile $detached Detached file
     * @param bool $verbose Verbose output
     * @return string Information message
     */
    public static function info(DetachedTimestampFile $detached, bool $verbose = false): string
    {
        $timestamp = $detached->timestamp;
        $hashOp = $detached->fileHashOp->hashlibName();
        $fileHash = Utils::bytesToHex($detached->fileDigest());
        $firstLine = "File $hashOp hash: $fileHash\n";

        try {
            if ($verbose) {
                return $firstLine . "Timestamp:\n" . $timestamp->strTree(0, 1);
            }
            return $firstLine . "Timestamp:\n" . $timestamp->strTree(0, 0);
        } catch (\Throwable $err) {
            return 'Error parsing info: ' . $err->getMessage();
        }
    }

    /**
     * Convert timestamp to JSON.
     *
     * @param array|Timestamp $ots OTS bytes or Timestamp
     * @return string JSON string
     */
    public static function json($ots): string
    {
        $json = [];

        if ($ots === null) {
            $json['result'] = 'KO';
            $json['error'] = 'No ots file';
            return json_encode($json);
        }

        $timestamp = null;

        if ($ots instanceof Timestamp) {
            $timestamp = $ots;
            $json['hash'] = Utils::bytesToHex($timestamp->msg);
        } else {
            try {
                $ctx = new StreamDeserializationContext($ots);
                $detachedTimestampFile = DetachedTimestampFile::deserialize($ctx);
                $timestamp = $detachedTimestampFile->timestamp;
                $json['hash'] = Utils::bytesToHex($timestamp->msg);
                $json['op'] = $detachedTimestampFile->fileHashOp->hashlibName();
            } catch (\Throwable $err) {
                $json['result'] = 'KO';
                $json['error'] = 'Error deserialization: ' . $err->getMessage();
                return json_encode($json);
            }
        }

        try {
            $json['result'] = 'OK';
            $json['timestamp'] = $timestamp->toJson();
        } catch (\Throwable $err) {
            $json['result'] = 'KO';
            $json['error'] = 'Error parsing info: ' . $err->getMessage();
        }

        return json_encode($json);
    }

    /**
     * Create a Merkle tree from detached timestamps.
     *
     * @param DetachedTimestampFile[] $fileTimestamps Array of detached files
     * @return Timestamp|null Merkle tip timestamp
     */
    public static function makeMerkleTree(array $fileTimestamps): ?Timestamp
    {
        $merkleRoots = [];

        foreach ($fileTimestamps as $fileTimestamp) {
            if (!($fileTimestamp instanceof DetachedTimestampFile)) {
                error_log('Invalid input');
                return null;
            }

            try {
                $bytesRandom16 = Utils::randBytes(16);
                $nonceAppendedStamp = $fileTimestamp->timestamp->add(new OpAppend($bytesRandom16));
                $merkleRoot = $nonceAppendedStamp->add(new OpSHA256());
                $merkleRoots[] = $merkleRoot;
            } catch (\Throwable $err) {
                return null;
            }
        }

        return Merkle::makeMerkleTree($merkleRoots);
    }

    /**
     * Stamp files with remote calendars.
     *
     * @param DetachedTimestampFile|DetachedTimestampFile[] $detaches Files to stamp
     * @param array $options Options (calendars, m)
     * @return Timestamp|null Created timestamp
     */
    public static function stamp($detaches, array $options = []): ?Timestamp
    {
        // Parse input detaches
        if ($detaches instanceof DetachedTimestampFile) {
            $detachedList = [$detaches];
        } elseif (is_array($detaches)) {
            $detachedList = $detaches;
        } else {
            throw new \InvalidArgumentException('Invalid input');
        }

        // Build merkle tree
        $merkleTip = self::makeMerkleTree($detachedList);
        if ($merkleTip === null) {
            throw new \RuntimeException('Failed to create Merkle tree');
        }

        // Default calendars
        $calendars = $options['calendars'] ?? Calendar::DEFAULT_AGGREGATORS;
        $m = $options['m'] ?? (count($calendars) >= 2 ? 2 : 1);

        if ($m <= 0 || $m > count($calendars)) {
            throw new \InvalidArgumentException('m cannot be greater than available calendars nor less than or equal to 0');
        }

        // Create timestamp from merkle root
        return self::createTimestamp($merkleTip, $calendars, $m);
    }

    /**
     * Create timestamp by submitting to calendars.
     *
     * @param Timestamp $timestamp Timestamp to submit
     * @param string[] $calendars Calendar URLs
     * @param int $m Minimum calendars required
     * @return Timestamp Updated timestamp
     */
    public static function createTimestamp(Timestamp $timestamp, array $calendars, int $m): Timestamp
    {
        $results = [];

        foreach ($calendars as $calendar) {
            $remote = new RemoteCalendar($calendar);
            echo "Submitting to remote calendar $calendar\n";
            try {
                $results[] = $remote->submit($timestamp->msg);
            } catch (\Throwable $err) {
                $results[] = $err;
            }
        }

        foreach ($results as $result) {
            if ($result instanceof Timestamp && !($result instanceof \Throwable)) {
                $timestamp->merge($result);
            }
        }

        return $timestamp;
    }

    /**
     * Verify a timestamp.
     *
     * @param DetachedTimestampFile $detachedStamped Stamped file
     * @param DetachedTimestampFile $detachedOriginal Original file
     * @param array $options Verification options
     * @return array Verification results
     * @throws \Exception If verification fails
     */
    public static function verify(DetachedTimestampFile $detachedStamped, DetachedTimestampFile $detachedOriginal, array $options = []): array
    {
        // Compare stamped vs original detached file
        if (!Utils::arrEq($detachedStamped->fileDigest(), $detachedOriginal->fileDigest())) {
            throw new \Exception('File does not match original!');
        }

        self::upgradeTimestamp($detachedStamped->timestamp, $options);
        return self::verifyTimestamp($detachedStamped->timestamp, $options);
    }

    /**
     * Verify timestamp attestations.
     *
     * @param Timestamp $timestamp Timestamp to verify
     * @param array $options Verification options
     * @return array Verification results indexed by chain
     */
    public static function verifyTimestamp(Timestamp $timestamp, array $options = []): array
    {
        $results = [];

        foreach ($timestamp->allAttestations() as $item) {
            $attestation = $item['attestation'];
            $msg = $item['msg'];
            try {
                $results[] = self::verifyAttestation($attestation, $msg, $options);
            } catch (\Throwable $err) {
                $results[] = $err;
            }
        }

        // Filter errors
        $errors = array_filter($results, fn($r) => $r instanceof VerificationError);
        if (count($errors) > 0) {
            throw reset($errors);
        }

        // Process successful results
        $outputs = [];
        $filtered = array_filter($results, fn($r) => !($r instanceof \Throwable));

        foreach ($filtered as $result) {
            $chain = $result['chain'];
            if (!isset($outputs[$chain]) || $result['attestedTime'] < $outputs[$chain]['timestamp']) {
                $outputs[$chain] = [
                    'timestamp' => $result['attestedTime'],
                    'height' => $result['height']
                ];
            }
        }

        return $outputs;
    }

    /**
     * Verify a single attestation.
     *
     * @param TimeAttestation $attestation Attestation to verify
     * @param int[] $msg Message digest
     * @param array $options Verification options
     * @return array Verification result
     */
    public static function verifyAttestation(TimeAttestation $attestation, array $msg, array $options = []): array
    {
        $liteVerify = function () use ($attestation, $msg, $options) {
            $chain = $options['chain'] ?? 'bitcoin';
            $esplora = new Esplora($options);

            $blockHash = $esplora->blockhash($attestation->height);
            echo "Lite-client verification, assuming block $blockHash is valid\n";

            $blockHeader = $esplora->block($blockHash);
            $attestedTime = $attestation->verifyAgainstBlockheader(array_reverse($msg), $blockHeader);

            return [
                'attestedTime' => $attestedTime,
                'chain' => $chain,
                'height' => $attestation->height
            ];
        };

        if ($attestation instanceof PendingAttestation) {
            throw new \Exception('PendingAttestation');
        } elseif ($attestation instanceof UnknownAttestation) {
            throw new \Exception('UnknownAttestation');
        } elseif ($attestation instanceof BitcoinBlockHeaderAttestation) {
            if ($options['ignoreBitcoinNode'] ?? false) {
                return $liteVerify();
            }

            // Try local Bitcoin node first, fall back to lite verify
            try {
                $bitcoinConf = Bitcoin::readBitcoinConf();
                $bitcoin = new BitcoinNode($bitcoinConf);
                $blockHeader = $bitcoin->getBlockHeader($attestation->height);

                return [
                    'attestedTime' => $attestation->verifyAgainstBlockheader(array_reverse($msg), $blockHeader),
                    'chain' => 'bitcoin',
                    'height' => $attestation->height
                ];
            } catch (\Throwable $err) {
                if (strpos($err->getMessage(), 'Invalid bitcoin.conf') !== false) {
                    error_log('Could not connect to local Bitcoin node');
                    return $liteVerify();
                }
                throw new VerificationError('Bitcoin verification failed: ' . $err->getMessage());
            }
        } elseif ($attestation instanceof LitecoinBlockHeaderAttestation) {
            throw new \Exception('Litecoin verification not available');
        }

        throw new \Exception('Unknown attestation type');
    }

    /**
     * Upgrade a timestamp.
     *
     * @param DetachedTimestampFile $detached Detached file
     * @param array $options Upgrade options
     * @return bool True if timestamp was changed
     */
    public static function upgrade(DetachedTimestampFile $detached, array $options = []): bool
    {
        return self::upgradeTimestamp($detached->timestamp, $options);
    }

    /**
     * Attempt to upgrade an incomplete timestamp.
     *
     * @param Timestamp $timestamp Timestamp to upgrade
     * @param array $options Upgrade options
     * @return bool True if timestamp was changed
     */
    public static function upgradeTimestamp(Timestamp $timestamp, array $options = []): bool
    {
        $changed = false;
        $attestations = $timestamp->allAttestations();

        foreach ($attestations as $item) {
            $msg = $item['msg'] ?? null;
            $attestation = $item['attestation'] ?? null;

            if (!is_array($msg) || !($attestation instanceof PendingAttestation)) {
                continue;
            }

            $candidateCalendars = [];
            if (isset($options['calendars']) && is_array($options['calendars']) && count($options['calendars']) > 0) {
                $candidateCalendars = $options['calendars'];
            } else {
                $candidateCalendars = array_merge([$attestation->uri], Calendar::DEFAULT_AGGREGATORS);
            }
            $candidateCalendars = array_values(array_unique($candidateCalendars));

            foreach ($candidateCalendars as $calendarUrl) {
                if (!is_string($calendarUrl) || $calendarUrl === '') {
                    continue;
                }

                try {
                    $remote = new RemoteCalendar($calendarUrl);
                    $upgradedStamp = $remote->getTimestamp($msg);
                    $timestamp->merge($upgradedStamp);
                    $changed = true;
                    break;
                } catch (\Throwable) {
                    // Try next calendar candidate for this pending attestation
                }
            }
        }

        return $changed;
    }
}
