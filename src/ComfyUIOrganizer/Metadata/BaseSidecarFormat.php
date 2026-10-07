<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Metadata;

use AppUtils\ArrayDataCollection;

/**
 * Shared lenient readers for sidecar formats. Each reader returns
 * `null` for an absent key or a value of another type, so that
 * "absent" stays distinguishable from "empty".
 */
abstract class BaseSidecarFormat implements SidecarFormatInterface
{
    protected function readString(ArrayDataCollection $data, string $key) : ?string
    {
        $value = $data->getKey($key);

        return is_string($value) ? $value : null;
    }

    protected function readInt(ArrayDataCollection $data, string $key) : ?int
    {
        $value = $data->getKey($key);

        return is_int($value) ? $value : null;
    }

    /**
     * Integers are accepted and widened, since JSON writers
     * emit `4` for `4.0`.
     */
    protected function readFloat(ArrayDataCollection $data, string $key) : ?float
    {
        $value = $data->getKey($key);

        if(is_float($value)) {
            return $value;
        }

        return is_int($value) ? (float)$value : null;
    }

    protected function readBool(ArrayDataCollection $data, string $key) : ?bool
    {
        $value = $data->getKey($key);

        return is_bool($value) ? $value : null;
    }

    /**
     * Whether the key holds a JSON object. The empty object decodes
     * to an empty array and counts as an object.
     */
    protected function isObjectSection(ArrayDataCollection $data, string $key) : bool
    {
        $value = $data->getKey($key);

        return is_array($value) && ($value === array() || !array_is_list($value));
    }

    /**
     * Rounds away floating-point noise, e.g. `6.200000000000001` becomes `6.2`.
     */
    protected function normalizeFloat(float|int $value) : string
    {
        return (string)round($value, 4);
    }
}
