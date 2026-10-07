<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Workflows;

use Closure;

/**
 * Owns a decoded ComfyUI workflow document.
 *
 * The whole document is kept verbatim, so unknown keys, non-array node
 * entries and the node order survive {@see self::toArray()} unchanged.
 */
class WorkflowData
{
    private const string KEY_NODES = 'nodes';
    private const string KEY_TYPE = 'type';
    private const string KEY_WIDGETS_VALUES = 'widgets_values';

    /**
     * @var array<int|string,mixed>
     */
    private array $data;

    /**
     * @param array<int|string,mixed> $data
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @param array<int|string,mixed> $data
     * @return self
     */
    public static function fromArray(array $data) : self
    {
        return new self($data);
    }

    public function hasNodes() : bool
    {
        return is_array($this->data[self::KEY_NODES] ?? null);
    }

    /**
     * @return array<int|string,mixed>
     */
    public function toArray() : array
    {
        return $this->data;
    }

    /**
     * Applies the resolver to the first widget value of every node of the
     * given type, if that value is a non-empty string.
     *
     * @param string $nodeType
     * @param Closure(string):string $resolver
     * @return int The number of values that were changed.
     */
    public function rewriteFirstWidgetString(string $nodeType, Closure $resolver) : int
    {
        $nodes = $this->data[self::KEY_NODES] ?? null;

        if(!is_array($nodes)) {
            return 0;
        }

        $changed = 0;

        foreach($nodes as $key => $node)
        {
            if(!is_array($node) || ($node[self::KEY_TYPE] ?? null) !== $nodeType) {
                continue;
            }

            $values = $node[self::KEY_WIDGETS_VALUES] ?? null;
            if(!is_array($values)) {
                continue;
            }

            $current = $values[0] ?? null;
            if(!is_string($current) || $current === '') {
                continue;
            }

            $new = $resolver($current);
            if($new === $current) {
                continue;
            }

            $values[0] = $new;
            $node[self::KEY_WIDGETS_VALUES] = $values;
            $nodes[$key] = $node;
            $changed++;
        }

        if($changed > 0) {
            $this->data[self::KEY_NODES] = $nodes;
        }

        return $changed;
    }
}
