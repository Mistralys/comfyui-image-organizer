<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests\Workflows;

use Mistralys\ComfyUIOrganizer\Workflows\WorkflowData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WorkflowDataTest extends TestCase
{
    private static function upper(string $value) : string
    {
        return strtoupper($value);
    }

    public function testRoundTripIsExact() : void
    {
        $workflow = array(
            'last_node_id' => 9,
            'nodes' => array(
                5 => array('id' => 5, 'type' => 'KSampler', 'flags' => array(), 'properties' => array()),
                'broken',
                3 => array('id' => 3, 'type' => 'LoraLoaderModelOnly', 'widgets_values' => array('a.safetensors', 1.0), 'config' => array()),
                null,
            ),
            'extra' => array('ds' => array('scale' => 1.5)),
            'version' => 0.4,
        );

        $data = WorkflowData::fromArray($workflow);

        $this->assertTrue($data->hasNodes());
        $this->assertSame($workflow, $data->toArray());
    }

    /**
     * @return array<string,array{0:array<int|string,mixed>}>
     */
    public static function noNodesProvider() : array
    {
        return array(
            'absent' => array(array('version' => 1)),
            'null' => array(array('nodes' => null)),
            'string' => array(array('nodes' => 'text')),
        );
    }

    /**
     * @param array<int|string,mixed> $workflow
     */
    #[DataProvider('noNodesProvider')]
    public function testHasNoNodes(array $workflow) : void
    {
        $data = WorkflowData::fromArray($workflow);

        $this->assertFalse($data->hasNodes());
        $this->assertSame(0, $data->rewriteFirstWidgetString('X', self::upper(...)));
        $this->assertSame($workflow, $data->toArray());
    }

    /**
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function skippedNodeProvider() : array
    {
        return array(
            'missing widgets_values' => array(array('type' => 'T')),
            'empty list' => array(array('type' => 'T', 'widgets_values' => array())),
            'non-array widgets_values' => array(array('type' => 'T', 'widgets_values' => 'abc')),
            'non-string first value' => array(array('type' => 'T', 'widgets_values' => array(5, 'abc'))),
            'empty-string first value' => array(array('type' => 'T', 'widgets_values' => array('', 'abc'))),
            'no type' => array(array('widgets_values' => array('abc'))),
            'non-string type' => array(array('type' => 5, 'widgets_values' => array('abc'))),
        );
    }

    /**
     * @param array<string,mixed> $node
     */
    #[DataProvider('skippedNodeProvider')]
    public function testRewriteSkipsNode(array $node) : void
    {
        $workflow = array('nodes' => array($node));
        $data = WorkflowData::fromArray($workflow);

        $this->assertSame(0, $data->rewriteFirstWidgetString('T', self::upper(...)));
        $this->assertSame($workflow, $data->toArray());
    }

    public function testRewriteFirstWidgetString() : void
    {
        $data = WorkflowData::fromArray(array(
            'nodes' => array(
                array('type' => 'T', 'widgets_values' => array('abc', 'def')),
            )
        ));

        $received = array();
        $count = $data->rewriteFirstWidgetString('T', function (string $value) use (&$received) : string {
            $received[] = $value;
            return strtoupper($value);
        });

        $this->assertSame(1, $count);
        $this->assertSame(array('abc'), $received);
        $this->assertSame(
            array('nodes' => array(array('type' => 'T', 'widgets_values' => array('ABC', 'def')))),
            $data->toArray()
        );
    }

    public function testRewriteTouchesOnlyMatchingNodeTypeAndFirstWidget() : void
    {
        $other = array('id' => 2, 'type' => 'Other', 'widgets_values' => array('keep', 'keep2'), 'mode' => 0);
        $unchanged = array('id' => 3, 'type' => 'T', 'widgets_values' => array('SAME', 'x'));
        $workflow = array(
            'extra' => array('a' => 'b'),
            'nodes' => array(
                $other,
                array('id' => 1, 'type' => 'T', 'order' => 1, 'widgets_values' => array('abc', 'second')),
                'broken',
                $unchanged,
                array('id' => 4, 'type' => 'T', 'widgets_values' => array('def')),
            )
        );

        $data = WorkflowData::fromArray($workflow);
        $count = $data->rewriteFirstWidgetString('T', self::upper(...));

        $this->assertSame(2, $count);

        $expected = $workflow;
        $expected['nodes'][1] = array('id' => 1, 'type' => 'T', 'order' => 1, 'widgets_values' => array('ABC', 'second'));
        $expected['nodes'][4] = array('id' => 4, 'type' => 'T', 'widgets_values' => array('DEF'));

        $this->assertSame($expected, $data->toArray());
    }
}
