<?php

declare(strict_types=1);

namespace OpenTimestamps\Timestamp;

use OpenTimestamps\Attestations\BitcoinBlockHeaderAttestation;
use OpenTimestamps\Attestations\LitecoinBlockHeaderAttestation;
use OpenTimestamps\Attestations\PendingAttestation;
use OpenTimestamps\Attestations\TimeAttestation;
use OpenTimestamps\Attestations\UnknownAttestation;
use OpenTimestamps\Exceptions\TypeError;
use OpenTimestamps\Exceptions\ValueError;
use OpenTimestamps\Ops\Op;
use OpenTimestamps\Ops\OpReverse;
use OpenTimestamps\Serialize\StreamDeserializationContext;
use OpenTimestamps\Serialize\StreamSerializationContext;
use OpenTimestamps\Utils;

/**
 * Timestamp class representing a proof that attestations commit to a message.
 */
class Timestamp
{
    /** @var int[] Message bytes */
    public array $msg;

    /** @var TimeAttestation[] List of attestations */
    public array $attestations = [];

    /** @var array<Op, Timestamp> Map of operations to sub-timestamps using object hash as key */
    private array $ops = [];

    /**
     * Set an operation mapping (used by Merkle).
     *
     * @param Op $op Operation
     * @param Timestamp $stamp Sub-timestamp
     */
    public function opsSet(Op $op, Timestamp $stamp): void
    {
        $this->ops[spl_object_id($op)] = ['op' => $op, 'stamp' => $stamp];
    }

    /**
     * Create a Timestamp object.
     *
     * @param int[] $msg Message bytes
     * @throws TypeError If msg is not an array
     * @throws ValueError If msg exceeds max length
     */
    public function __construct(array $msg)
    {
        if (count($msg) === 0 || !is_array($msg)) {
            throw new TypeError('Expected msg to be bytes; got ' . gettype($msg));
        } elseif (count($msg) > Op::MAX_RESULT_LENGTH) {
            throw new TypeError('Message exceeds Op length limit');
        }
        $this->msg = $msg;
    }

    /**
     * Get the digest (message).
     *
     * @return int[] Message bytes
     */
    public function getDigest(): array
    {
        return $this->msg;
    }

    /**
     * Deserialize a Timestamp.
     *
     * @param StreamDeserializationContext $ctx Context
     * @param int[] $initialMsg Initial message
     * @return Timestamp Deserialized timestamp
     */
    public static function deserialize(StreamDeserializationContext $ctx, array $initialMsg): Timestamp
    {
        $self = new Timestamp($initialMsg);

        $doTagOrAttestation = function (int $tag, array $initialMsg) use ($ctx, $self): void {
            if ($tag === 0x00) {
                $attestation = TimeAttestation::deserialize($ctx);
                $self->attestations[] = $attestation;
            } else {
                $op = Op::deserializeFromTag($ctx, $tag);
                if ($op !== null) {
                    $result = $op->call($initialMsg);
                    $stamp = Timestamp::deserialize($ctx, $result);
                    $self->ops[spl_object_id($op)] = ['op' => $op, 'stamp' => $stamp];
                }
            }
        };

        $tag = $ctx->readBytes(1)[0];
        while ($tag === 0xff) {
            $current = $ctx->readBytes(1)[0];
            $doTagOrAttestation($current, $initialMsg);
            $tag = $ctx->readBytes(1)[0];
        }
        $doTagOrAttestation($tag, $initialMsg);

        return $self;
    }

    /**
     * Serialize the timestamp.
     *
     * @param StreamSerializationContext $ctx Context
     * @throws ValueError If timestamp is empty
     */
    public function serialize(StreamSerializationContext $ctx): void
    {
        if (count($this->attestations) === 0 && count($this->ops) === 0) {
            throw new ValueError("An empty timestamp can't be serialized");
        }

        // Sort attestations
        $sortedAttestations = $this->attestations;
        usort($sortedAttestations, fn($a, $b) => $a->compareTo($b));

        if (count($sortedAttestations) > 1) {
            for ($i = 0; $i < count($sortedAttestations) - 1; $i++) {
                $ctx->writeBytes([0xff, 0x00]);
                $sortedAttestations[$i]->serialize($ctx);
            }
        }

        if (count($this->ops) === 0) {
            if (count($sortedAttestations) > 0) {
                $ctx->writeByte(0x00);
                $sortedAttestations[count($sortedAttestations) - 1]->serialize($ctx);
            }
        } elseif (count($this->ops) > 0) {
            if (count($sortedAttestations) > 0) {
                $ctx->writeBytes([0xff, 0x00]);
                $sortedAttestations[count($sortedAttestations) - 1]->serialize($ctx);
            }

            $index = 0;
            foreach ($this->ops as $item) {
                if ($index < count($this->ops) - 1) {
                    $ctx->writeBytes([0xff]);
                    $index++;
                }
                $item['op']->serialize($ctx);
                $item['stamp']->serialize($ctx);
            }
        }
    }

    /**
     * Merge another timestamp into this one.
     *
     * @param Timestamp $other Other timestamp
     * @throws ValueError If not a Timestamp or messages differ
     */
    public function merge(Timestamp $other): void
    {
        if (!Utils::arrEq($this->msg, $other->msg)) {
            throw new ValueError("Can't merge timestamps for different messages together");
        }

        foreach ($other->attestations as $attestation) {
            $this->attestations[] = $attestation;
        }

        foreach ($other->ops as $item) {
            $otherOp = $item['op'];
            $otherOpStamp = $item['stamp'];

            $found = null;
            foreach ($this->ops as $ourItem) {
                if ($ourItem['op']->equals($otherOp)) {
                    $found = $ourItem;
                    break;
                }
            }

            if ($found === null) {
                $result = $otherOp->call($this->msg);
                $ourOpStamp = new Timestamp($result);
                $this->ops[spl_object_id($otherOp)] = ['op' => $otherOp, 'stamp' => $ourOpStamp];
            } else {
                $ourOpStamp = $found['stamp'];
            }
            $ourOpStamp->merge($otherOpStamp);
        }
    }

    /**
     * Get all attestations recursively.
     *
     * @return array<int[], TimeAttestation> Map of messages to attestations
     */
    public function allAttestations(): array
    {
        $map = [];
        foreach ($this->attestations as $attestation) {
            $map[Utils::bytesToHex($this->msg)] = ['msg' => $this->msg, 'attestation' => $attestation];
        }
        foreach ($this->ops as $item) {
            $subMap = $item['stamp']->allAttestations();
            foreach ($subMap as $key => $value) {
                $map[$key] = $value;
            }
        }
        return $map;
    }

    /**
     * Add an operation and return the sub-stamp.
     *
     * @param Op $op Operation to add
     * @return Timestamp Sub-timestamp
     */
    public function add(Op $op): Timestamp
    {
        foreach ($this->ops as $item) {
            if ($item['op']->equals($op)) {
                return $item['stamp'];
            }
        }

        $result = $op->call($this->msg);
        $stamp = new Timestamp($result);
        $this->ops[spl_object_id($op)] = ['op' => $op, 'stamp' => $stamp];
        return $stamp;
    }

    /**
     * Check if timestamp is complete.
     *
     * @return bool True if complete
     */
    public function isTimestampComplete(): bool
    {
        foreach ($this->allAttestations() as $item) {
            $attestation = $item['attestation'];
            if ($attestation instanceof BitcoinBlockHeaderAttestation ||
                $attestation instanceof LitecoinBlockHeaderAttestation ||
                $attestation instanceof UnknownAttestation) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get directly verified timestamps.
     *
     * @return Timestamp[] Array of timestamps with attestations
     */
    public function directlyVerified(): array
    {
        if (count($this->attestations) > 0) {
            return [$this];
        }
        $array = [];
        foreach ($this->ops as $item) {
            $result = $item['stamp']->directlyVerified();
            $array = array_merge($array, $result);
        }
        return $array;
    }

    /**
     * Get string tree representation.
     *
     * @param int $indent Indentation level
     * @param int $verbosity Verbosity level
     * @return string Tree string
     */
    public function strTree(int $indent = 0, int $verbosity = 0): string
    {
        $strResult = function (int $verb, ?array $parameter, ?array $result): string {
            $rr = '';
            if ($verb > 0 && $result !== null) {
                $rr .= ' == ';
                $resultHex = Utils::bytesToHex($result);
                if ($parameter === null) {
                    $rr .= $resultHex;
                } else {
                    $parameterHex = Utils::bytesToHex($parameter);
                    $idx = strpos($resultHex, $parameterHex);
                    if ($idx !== false && $idx === 0) {
                        $rr .= '**' . $parameterHex . '**' . substr($resultHex, strlen($parameterHex));
                    } else {
                        $rr .= $resultHex;
                    }
                }
            }
            return $rr;
        };

        $indention = str_repeat('    ', $indent);
        $r = '';

        foreach ($this->attestations as $attestation) {
            $r .= $indention . 'verify ' . $attestation->__toString() . $strResult($verbosity, $this->msg) . "\n";
            if ($attestation instanceof BitcoinBlockHeaderAttestation) {
                $tx = Utils::bytesToHex((new OpReverse())->call($this->msg));
                $r .= $indention . '# Bitcoin block merkle root ' . $tx . "\n";
            }
            if ($attestation instanceof LitecoinBlockHeaderAttestation) {
                $tx = Utils::bytesToHex((new OpReverse())->call($this->msg));
                $r .= $indention . '# Litecoin block merkle root ' . $tx . "\n";
            }
        }

        if (count($this->ops) > 1) {
            foreach ($this->ops as $item) {
                $op = $item['op'];
                $timestamp = $item['stamp'];
                $curRes = $op->call($this->msg);
                $curPar = $op->arg ?? null;
                $r .= $indention . ' -> ' . $op->__toString() . $strResult($verbosity, $curPar, $curRes) . "\n";
                $r .= $timestamp->strTree($indent + 1, $verbosity);
            }
        } elseif (count($this->ops) > 0) {
            $item = reset($this->ops);
            $op = $item['op'];
            $stamp = $item['stamp'];
            $curRes = $op->call($this->msg);
            $curPar = $op->arg ?? null;
            $r .= $indention . $op->__toString() . $strResult($verbosity, $curPar, $curRes) . "\n";
            $r .= $stamp->strTree($indent, $verbosity);
        }

        return $r;
    }

    /**
     * Convert to JSON representation.
     *
     * @param int $fork Fork counter
     * @return array JSON-like array
     */
    public function toJson(int $fork = 0): array
    {
        $json = [];

        if (count($this->attestations) > 0) {
            $json['attestations'] = [];
            foreach ($this->attestations as $attestation) {
                $item = ['fork' => $fork];
                if ($attestation instanceof PendingAttestation) {
                    $item['type'] = 'PendingAttestation';
                    $item['param'] = $attestation->uri;
                } elseif ($attestation instanceof UnknownAttestation) {
                    $item['type'] = 'UnknownAttestation';
                    $item['param'] = $attestation->payload;
                } elseif ($attestation instanceof BitcoinBlockHeaderAttestation) {
                    $item['type'] = 'BitcoinBlockHeaderAttestation';
                    $item['param'] = $attestation->height;
                    $item['merkle'] = Utils::bytesToHex(array_reverse($this->msg));
                } elseif ($attestation instanceof LitecoinBlockHeaderAttestation) {
                    $item['type'] = 'LitecoinBlockHeaderAttestation';
                    $item['param'] = $attestation->height;
                    $item['merkle'] = Utils::bytesToHex(array_reverse($this->msg));
                }
                $json['attestations'][] = $item;
            }
        }

        $json['result'] = Utils::bytesToHex($this->msg);

        if (count($this->ops) > 1) {
            $fork++;
        }

        if (count($this->ops) > 0) {
            $json['ops'] = [];
            $count = 0;
            foreach ($this->ops as $item) {
                $op = $item['op'];
                $timestamp = $item['stamp'];
                $opItem = [
                    'fork' => $fork + $count,
                    'op' => $op->tagName(),
                    'arg' => $op->arg !== null ? Utils::bytesToHex($op->arg) : '',
                    'result' => Utils::bytesToHex($timestamp->msg),
                    'timestamp' => $timestamp->toJson($fork + $count)
                ];
                $json['ops'][] = $opItem;
                $count++;
            }
        }

        return $json;
    }

    /**
     * Check equality with another timestamp.
     *
     * @param mixed $another Another object
     * @return bool True if equal
     */
    public function equals(mixed $another): bool
    {
        if (!$another instanceof Timestamp) {
            return false;
        }

        if (!Utils::arrEq($this->getDigest(), $another->getDigest())) {
            return false;
        }

        if (count($this->attestations) !== count($another->attestations)) {
            return false;
        }

        for ($i = 0; $i < count($this->attestations); $i++) {
            if (!$this->attestations[$i]->equals($another->attestations[$i])) {
                return false;
            }
        }

        if (count($this->ops) !== count($another->ops)) {
            return false;
        }

        // Note: Full ops comparison would require more complex logic
        return true;
    }
}
