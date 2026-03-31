<?php

declare(strict_types=1);

namespace OpenTimestamps;

/**
 * Utils class providing utility functions.
 */
class Utils
{
    /**
     * Convert a hex string to a byte array.
     *
     * @param string $hex Hex string
     * @return int[] Byte array
     */
    public static function hexToBytes(string $hex): array
    {
        $bytes = [];
        for ($c = 0; $c < strlen($hex); $c += 2) {
            $bytes[] = intval(substr($hex, $c, 2), 16);
        }
        return $bytes;
    }

    /**
     * Convert a byte array to a hex string.
     *
     * @param int[]|array $bytes Byte array
     * @return string Hex string
     */
    public static function bytesToHex(array $bytes): string
    {
        $hex = '';
        foreach ($bytes as $byte) {
            $hex .= sprintf('%02x', $byte & 0xff);
        }
        return $hex;
    }

    /**
     * Convert chars to hexadecimal representation.
     *
     * @param string $chars String of characters
     * @return string Hex string
     */
    public static function charsToHex(string $chars): string
    {
        $hex = '';
        for ($i = 0; $i < strlen($chars); $i++) {
            $b = ord($chars[$i]);
            $hex .= sprintf('%02x', $b);
        }
        return $hex;
    }

    /**
     * Convert char to byte representation.
     *
     * @param string $char Single character
     * @return int Byte value
     */
    public static function charToByte(string $char): int
    {
        return ord($char);
    }

    /**
     * Convert chars to bytes representation.
     *
     * @param string $chars String of characters
     * @return int[] Byte array
     */
    public static function charsToBytes(string $chars): array
    {
        $bytes = [];
        for ($i = 0; $i < strlen($chars); $i++) {
            $bytes[] = ord($chars[$i]);
        }
        return $bytes;
    }

    /**
     * Convert buffer to string.
     *
     * @param int[] $buffer Byte array
     * @return string String
     */
    public static function bytesToChars(array $buffer): string
    {
        $chars = '';
        foreach ($buffer as $byte) {
            $chars .= chr($byte);
        }
        return $chars;
    }

    /**
     * Convert string to byte array.
     *
     * @param string $str Input string
     * @return int[] Byte array
     */
    public static function toBytes(string $str): array
    {
        $arr = [];
        for ($i = 0; $i < strlen($str); $i++) {
            $arr[] = ord($str[$i]);
        }
        return $arr;
    }

    /**
     * Convert array to bytes (ensure integer values).
     *
     * @param array $buffer Input array
     * @return int[] Byte array
     */
    public static function arrayToBytes(array $buffer): array
    {
        $bytes = [];
        foreach ($buffer as $value) {
            $bytes[] = (int)$value;
        }
        return $bytes;
    }

    /**
     * Compare two arrays lexicographically.
     *
     * @param int[] $left Left array
     * @param int[] $right Right array
     * @return int Comparison result
     */
    public static function arrCompare(array $left, array $right): int
    {
        $len = min(count($left), count($right));
        for ($i = 0; $i < $len; $i++) {
            $a = $left[$i] & 0xff;
            $b = $right[$i] & 0xff;
            if ($a !== $b) {
                return $a - $b;
            }
        }
        return count($left) - count($right);
    }

    /**
     * Check if two arrays are equal.
     *
     * @param int[] $arr1 First array
     * @param int[] $arr2 Second array
     * @return bool True if equal
     */
    public static function arrEq(array $arr1, array $arr2): bool
    {
        if (count($arr1) !== count($arr2)) {
            return false;
        }
        for ($i = 0; $i < count($arr1); $i++) {
            if ($arr1[$i] !== $arr2[$i]) {
                return false;
            }
        }
        return true;
    }

    /**
     * Generate random bytes.
     *
     * @param int $n Number of bytes
     * @return int[] Random byte array
     * @throws \Exception If random generation fails
     */
    public static function randBytes(int $n): array
    {
        $randomBytes = random_bytes($n);
        $bytes = [];
        for ($i = 0; $i < $n; $i++) {
            $bytes[] = ord($randomBytes[$i]);
        }
        return $bytes;
    }

    /**
     * Generate random hex string.
     *
     * @param int $n Length of string
     * @return string Random hex string
     */
    public static function randString(int $n): string
    {
        if ($n <= 0) {
            return '';
        }
        $randomBytes = bin2hex(random_bytes((int)ceil($n / 2)));
        return substr($randomBytes, 0, $n);
    }

    /**
     * Read file contents as bytes.
     *
     * @param string $filename File path
     * @return int[] Byte array
     * @throws \RuntimeException If file cannot be read
     */
    public static function readFileAsBytes(string $filename): array
    {
        $content = file_get_contents($filename);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: $filename");
        }
        $bytes = [];
        for ($i = 0; $i < strlen($content); $i++) {
            $bytes[] = ord($content[$i]);
        }
        return $bytes;
    }

    /**
     * Write bytes to file.
     *
     * @param string $filename File path
     * @param int[] $bytes Byte array
     * @return int|false Number of bytes written or false on failure
     */
    public static function writeBytesToFile(string $filename, array $bytes): int|false
    {
        $content = '';
        foreach ($bytes as $byte) {
            $content .= chr($byte);
        }
        return file_put_contents($filename, $content);
    }
}
