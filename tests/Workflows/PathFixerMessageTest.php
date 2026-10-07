<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests\Workflows;

use Mistralys\ComfyUIOrganizer\Workflows\PathFixerMessage;
use PHPUnit\Framework\TestCase;

final class PathFixerMessageTest extends TestCase
{
    public function testStringArgumentsAreHtmlEscaped() : void
    {
        $message = new PathFixerMessage(
            PathFixerMessage::TYPE_NOTICE,
            'Replacing LoRA path [%s] with [%s].',
            '<img src=x onerror=alert(1)>my_lora.safetensors',
            'sub\\my_"lora".safetensors'
        );

        $this->assertSame(
            'Notice: Replacing LoRA path [&lt;img src=x onerror=alert(1)&gt;my_lora.safetensors] with [sub\\my_&quot;lora&quot;.safetensors].',
            $message->render()
        );
    }

    public function testNumericArgumentsAreKept() : void
    {
        $message = new PathFixerMessage(PathFixerMessage::TYPE_WARNING, 'Found %d files (%.1f%%).', 3, 42.5);

        $this->assertSame('Warning: Found 3 files (42.5%).', $message->render());
    }

    public function testMessageTemplateIsNotEscaped() : void
    {
        $message = new PathFixerMessage(PathFixerMessage::TYPE_NOTICE, 'Created <b>backup</b> %s.', 'a&b.json');

        $this->assertSame('Notice: Created <b>backup</b> a&amp;b.json.', $message->render());
    }

    public function testMessageWithoutArgumentsIsLeftUntouched() : void
    {
        $message = new PathFixerMessage(PathFixerMessage::TYPE_SUCCESS, '100% done');

        $this->assertSame('Success: 100% done', $message->render());
    }
}
