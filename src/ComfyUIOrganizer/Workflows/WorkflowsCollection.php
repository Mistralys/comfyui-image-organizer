<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Workflows;

use AppUtils\Collections\BaseStringPrimaryCollection;
use Mistralys\ComfyUIOrganizer\ComfyUI;

/**
 * @method WorkflowFile getByID(string $id)
 * @method WorkflowFile[] getAll()
 */
class WorkflowsCollection extends BaseStringPrimaryCollection
{
    private static ?self $instance = null;

    public static function create() : self
    {
        if(self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getDefaultID(): string
    {
        return $this->getAutoDefault();
    }

    public function getWorkflowFiles() : array
    {
        return ComfyUI::create()->getWorkflowsFolder()
            ->createFileFinder()
            ->includeExtension('json')
            ->getFiles()
            ->typeJSON();
    }

    protected function registerItems(): void
    {
        foreach($this->getWorkflowFiles() as $file) {
            $this->registerItem(new WorkflowFile($file));
        }
    }
}
