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
    public function __construct(string $type, string|StringableInterface $message, ...$args)
    {
        $this->type = $type;
        $this->message = (string)$message;

        if(!empty($args)) {
            $this->message = sprintf($this->message, ...$args);
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
