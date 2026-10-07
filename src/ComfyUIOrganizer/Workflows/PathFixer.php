<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Workflows;

use AppUtils\FileHelper;
use AppUtils\FileHelper\FileInfo;
use AppUtils\FileHelper\FolderInfo;
use AppUtils\FileHelper\JSONFile;
use AppUtils\Interfaces\StringableInterface;
use Mistralys\ComfyUIOrganizer\LoRAs\LoRAsCollection;
use Mistralys\ComfyUIOrganizer\OrganizerApp;

class PathFixer
{
    private JSONFile $workflowFile;
    /** @var array<string, array{relativePath: string, officialFileName: string, loraLabel: string}> */
    private array $loraFileIndex = array();

    /** @var PathFixerMessage[] */
    private array $messages = array();

    public function __construct(JSONFile $workflowFile)
    {
        $this->workflowFile = $workflowFile;
    }

    public function getLoRAFolder() : FolderInfo
    {
        return FolderInfo::factory(OrganizerApp::create()->getComfyFolder().'/models/loras');
    }

    private function addNotice(string|StringableInterface $message, string|int|float ...$args) : self
    {
        $this->messages[] = new PathFixerMessage(PathFixerMessage::TYPE_NOTICE, $message, ...$args);
        return $this;
    }

    private function addWarning(string|StringableInterface $message, string|int|float ...$args) : self
    {
        $this->messages[] = new PathFixerMessage(PathFixerMessage::TYPE_WARNING, $message, ...$args);
        return $this;
    }

    /**
     * Goes through all known LoRA files and builds an index
     * of their file names. This is used to match files found
     * in the ComfyUI `loras` folder with known LoRAs.
     */
    private function buildFileIndex() : void
    {
        $rootFolder = $this->getLoRAFolder()->getPath();

        $knownFiles = array();
        foreach(LoRAsCollection::getInstance()->getAll() as $lora) {
            $files = $lora->getFiles();
            if(empty($files)) {
                $this->addWarning(
                    'LoRA [%s] has no file specified in any of the JSON files.',
                    $lora->getID()
                );
                continue;
            }

            foreach($files as $knownFile) {
                $knownFiles[mb_strtolower($knownFile)] = $lora->getLabel();
            }
        }

        foreach($this->getLoRAFiles() as $file)
        {
            $name = mb_strtolower($file->getName());

            $lora = null;
            $useFile = null;
            foreach($knownFiles as $knownFile => $label)
            {
                // The original file name is only prefixed, so we can use the
                // end of the file name to match it.
                if($name === $knownFile || str_ends_with($name, $knownFile)) {
                    $lora = $label;
                    $useFile = $knownFile;
                    break;
                }
            }

            if($lora === null || $useFile === null) {
                $this->addNotice(
                    'LoRA file [%s] not found in known LoRAs. Missing from collection, or name mismatch?',
                    $file->getName()
                );
                continue;
            }

            $this->loraFileIndex[$name] = array(
                'relativePath' => str_replace('/', '\\', FileHelper::relativizePath($file->getPath(), $rootFolder)),
                'officialFileName' => $useFile,
                'loraLabel' => $lora
            );
        }
    }

    /**
     * @return FileHelper\FileInfo[]
     */
    private function getLoRAFiles() : array
    {
        return $this->getLoRAFolder()
            ->createFileFinder()
            ->includeExtension('safetensors')
            ->makeRecursive()
            ->getFiles()
            ->typeANY();
    }

    public function fixPaths() : self
    {
        $this->buildFileIndex();

        $workflow = WorkflowData::fromArray($this->workflowFile->getData());

        if(!$workflow->hasNodes()) {
            return $this;
        }

        $changed = $workflow->rewriteFirstWidgetString('LoraLoaderModelOnly', $this->resolvePath(...));

        if($changed > 0)
        {
            $this->createBackup();

            $this->workflowFile->putData($workflow->toArray());

            // Fix empty arrays that should be objects.
            // This is a quirk of ComfyUI's JSON handling.
            //
            $content = $this->workflowFile->getContents();
            $objectProps = array('flags', 'config', 'properties');
            foreach($objectProps as $prop) {
                $content = str_replace('"'.$prop.'":[]', '"'.$prop.'":{}', $content);
            }

            file_put_contents($this->workflowFile->getPath(), $content);
        }
        else
        {
            $this->addNotice('No changes necessary, workflow paths are up to date.');
        }

        return $this;
    }

    private function createBackup() : void
    {
        $backup = FileInfo::factory(str_replace('.json', '.json.bak.'.date('YmdHis'), $this->workflowFile->getPath()));
        $this->workflowFile->copyTo($backup);

        $this->addNotice('Created workflow backup under %s.', $backup->getName());
    }

    /**
     * @return PathFixerMessage[]
     */
    public function getMessages() : array
    {
        return $this->messages;
    }

    private function resolvePath(string $widgetValue) : string
    {
        $haystack = mb_strtolower($widgetValue);

        foreach($this->loraFileIndex as $info)
        {
            if(str_ends_with($haystack, $info['officialFileName']) && $haystack !== strtolower($info['relativePath']))
            {
                $this->addNotice(
                    'Replacing LoRA path [%s] with [%s].',
                    $widgetValue,
                    $info['relativePath']
                );

                return $info['relativePath'];
            }
        }

        return $widgetValue;
    }
}