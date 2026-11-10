<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Workflows;

use AppUtils\Interfaces\StringableInterface;

class PathFixerMessage implements StringableInterface
{
    public const string TYPE_ERROR = 'error';
    public const string TYPE_WARNING = 'warning';
    public const string TYPE_SUCCESS = 'success';
    public const string TYPE_NOTICE = 'notice';

    private string $type;
    private string $message;

    /**
     * `$message` is a trusted HTML template and is not escaped. String arguments are HTML-escaped here,
     * as they carry untrusted workflow, file or LoRA data: pass them as arguments, never in the
     * template, and never pre-escaped.
     */
    public function __construct(string $type, string|StringableInterface $message, string|int|float ...$args)
    {
        $this->type = $type;
        $this->message = (string)$message;

        if(!empty($args)) {
            $escaped = array_map(
                static fn(string|int|float $arg) : string|int|float => is_string($arg) ? htmlspecialchars($arg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $arg,
                $args
            );

            $this->message = sprintf($this->message, ...$escaped);
        }
    }

    public function __toString(): string
    {
        return $this->render();
    }

    public function render() : string
    {
        $typeLabel = match($this->type) {
            self::TYPE_ERROR => 'Error',
            self::TYPE_WARNING => 'Warning',
            self::TYPE_SUCCESS => 'Success',
            default => 'Notice'
        };

        return $typeLabel.': '.$this->message;
    }
}
