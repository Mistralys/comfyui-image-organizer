<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Workflows;

use AppUtils\ConvertHelper;
use AppUtils\FileHelper\JSONFile;
use AppUtils\Interfaces\StringPrimaryRecordInterface;
use DateTime;
use function AppUtils\sb;

class WorkflowFile implements StringPrimaryRecordInterface
{
    private JSONFile $file;
    private string $id;

    public function __construct(JSONFile $file)
    {
        $this->file = $file;
        $this->id = ConvertHelper::string2shortHash($file->getBaseName());
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function getLabel() : string
    {
        return $this->file->getBaseName();
    }

    public function getLabelURLFixPaths() : string
    {
        return (string)sb()->link($this->getLabel(), $this->url()->fixPaths());
    }

    public function getModifiedDate() : DateTime
    {
        return $this->file->requireModifiedDate();
    }

    public function getFile() : JSONFile
    {
        return $this->file;
    }

    public function url() : WorkflowFileURL
    {
        return new WorkflowFileURL($this);
    }
}
