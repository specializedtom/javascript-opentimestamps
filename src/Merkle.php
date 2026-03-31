<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * Merkle tree operations for timestamps.
 */
class Merkle
{
    /**
     * Concatenate left and right, then perform SHA256 on them.
     *
     * @param Timestamp|array $left Left timestamp or bytes
     * @param Timestamp|array $right Right timestamp or bytes
     * @return Timestamp Result timestamp
     */
    public static function catThenUnaryOp($left, $right): Timestamp
    {
        if (!($left instanceof Timestamp)) {
            $left = new Timestamp($left);
        }
        if (!($right instanceof Timestamp)) {
            $right = new Timestamp($right);
        }

        // Prepend left.msg to right
        $rightPrependStamp = $right->add(new OpPrepend($left->msg));
        $left->opsSet(new OpAppend($right->msg), $rightPrependStamp);

        // Apply SHA256
        return $rightPrependStamp->add(new OpSHA256());
    }

    /**
     * Concatenate and SHA256.
     *
     * @param Timestamp|array $left Left timestamp or bytes
     * @param Timestamp|array $right Right timestamp or bytes
     * @return Timestamp Result timestamp
     */
    public static function catSha256($left, $right): Timestamp
    {
        return self::catThenUnaryOp($left, $right);
    }

    /**
     * Concatenate and double SHA256.
     *
     * @param Timestamp|array $left Left timestamp or bytes
     * @param Timestamp|array $right Right timestamp or bytes
     * @return Timestamp Result timestamp
     */
    public static function catSha256d($left, $right): Timestamp
    {
        $sha256Timestamp = self::catSha256($left, $right);
        $opSHA256 = new OpSHA256();
        $res = $sha256Timestamp->add($opSHA256);
        return $res;
    }

    /**
     * Build a Merkle tree from timestamps.
     *
     * @param Timestamp[] $timestamps Array of timestamps
     * @return Timestamp|null Tip of the Merkle tree
     */
    public static function makeMerkleTree(array $timestamps): ?Timestamp
    {
        if (count($timestamps) === 0) {
            return null;
        }

        $stamps = $timestamps;

        while (count($stamps) > 1) {
            $nextStamps = [];
            $prevStamp = null;

            foreach ($stamps as $stamp) {
                if ($prevStamp === null) {
                    $prevStamp = $stamp;
                } else {
                    $nextStamps[] = self::catSha256($prevStamp, $stamp);
                    $prevStamp = null;
                }
            }

            if ($prevStamp !== null) {
                $nextStamps[] = $prevStamp;
            }

            $stamps = $nextStamps;
        }

        return $stamps[0] ?? null;
    }
}

// Add opsSet method to Timestamp via trait or update Timestamp class
// We need to add this helper to Timestamp class
