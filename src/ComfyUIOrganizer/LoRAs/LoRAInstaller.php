<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\LoRAs;

use Closure;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use FilesystemIterator;
use SplFileInfo;
use Throwable;

/**
 * Copies an explicitly supplied local path before saving its registry entry.
 * Basenames remain registry-only. Only existing folders inside the LoRA root
 * are accepted; existing files are reused only when their contents match.
 */
final class LoRAInstaller
{
    public const array DEFAULT_CATEGORY_FOLDERS = array(
        'FLUX' => array(
            'Styles' => 'FLUX\ArtStyles',
            'Scifi' => 'FLUX\Scifi',
            'Fantasy' => 'FLUX\Fantasy',
            'Characters' => 'FLUX\Characters',
            'NSFW' => 'FLUX\NSFW',
            'XXX' => 'FLUX\NSFW'
        ),
        'SDXL' => array(
            'Styles' => 'SDXL\ArtStyles',
            'Scifi' => 'SDXL\SciFi',
            'Fantasy' => 'SDXL\Fantasy',
            'Details' => 'SDXL\Detailers',
            'NSFW' => 'SDXL\zzNSFW',
            'XXX' => 'SDXL\zzNSFW'
        )
    );

    /** @var array<string,array<string,string>> */
    private array $folders = self::DEFAULT_CATEGORY_FOLDERS;
    private ?string $lastDestination = null;
    private bool $reusingFile = false;

    /**
     * @param mixed $folderOverrides Optional configuration: family => category => relative folder.
     * @param (Closure(?string,list<string>,string,string):string)|null $chooseDestination
     *        Suggested relative folder, existing folders, base model, category.
     */
    public function __construct(
        private readonly string $root,
        mixed $folderOverrides = array(),
        private readonly ?string $destination = null,
        private readonly ?Closure $chooseDestination = null
    ) {
        if(!is_array($folderOverrides)) {
            throw new RuntimeException('APP_LORA_CATEGORY_FOLDERS must be an array of model families and categories.');
        }
        foreach($folderOverrides as $family => $categories) {
            if(!is_string($family) || !is_array($categories)) {
                throw new RuntimeException('Invalid model family in APP_LORA_CATEGORY_FOLDERS.');
            }
            foreach($categories as $category => $folder) {
                if(!is_string($category) || !is_string($folder)) {
                    throw new RuntimeException('LoRA category folder mappings must contain string category names and paths.');
                }
                self::relativePath($folder);
                $this->folders[$family][$category] = $folder;
            }
        }
    }

    /** Validates explicit source paths before an API request or interactive prompts. */
    public function validateInput(string $input) : void
    {
        if($this->sourcePath($input) === null && $this->destination !== null) {
            throw new RuntimeException('--destination requires a local source path, not just a filename.');
        }
    }

    public function getLastDestination() : ?string
    {
        return $this->lastDestination;
    }

    public function isReusingFile() : bool
    {
        return $this->reusingFile;
    }

    /**
     * Runs the registry save only after a complete copy has been published.
     * Uses a same-directory hard link for atomic, no-overwrite publication.
     * Rolls back only a newly published, unchanged copy if saving fails.
     *
     * @param Closure():LoRA $save Must honor $dryRun; dry runs never write registry data.
     */
    public function run(string $input, LoRAModel $model, string $category, bool $dryRun, Closure $save) : LoRA
    {
        $this->lastDestination = null;
        $this->reusingFile = false;
        $this->validateInput($input);
        $source = $this->sourcePath($input);
        if($source === null) {
            return $save();
        }

        $relative = $this->destination;
        if($relative === null) {
            $family = self::modelFamily($model->getModelName());
            $suggested = $family !== null ? ($this->folders[$family][$category] ?? null) : null;
            if($this->chooseDestination !== null) {
                $relative = ($this->chooseDestination)($suggested, $this->getDestinations(),
                    $model->getModelName(), $category);
            } elseif($suggested !== null) {
                $relative = $suggested;
            } else {
                throw new RuntimeException('No destination mapping for this model/category. Choose an existing folder with --destination.');
            }
        }
        $folder = $this->destinationPath($relative);
        $filename = $model->getFile();
        if($filename !== basename(str_replace('\\', '/', $filename)) || str_contains($filename, ':') || $filename === '') {
            throw new RuntimeException('The registry filename must be a basename.');
        }
        $target = $folder.DIRECTORY_SEPARATOR.$filename;
        $this->lastDestination = $target;
        if(is_link($target)) {
            throw new RuntimeException('Refusing to use a symbolic link as the destination file: '.$target);
        }
        if(file_exists($target)) {
            if(!is_file($target) || !$this->sameContents($source, $target)) {
                throw new RuntimeException('Destination already exists with different contents; nothing was overwritten: '.$target);
            }
            $this->reusingFile = true;
            return $save();
        }
        if($dryRun) {
            return $save();
        }

        $hash = self::digest($source);
        $temporary = tempnam($folder, '.lora-copy-');
        if($temporary === false) {
            throw new RuntimeException('Cannot create a temporary model file in '.$folder);
        }
        $published = false;
        try {
            if(!copy($source, $temporary) || self::digest($temporary) !== $hash) {
                throw new RuntimeException('Could not copy the complete LoRA file; registry unchanged.');
            }
            // Unlike rename(), link() cannot replace a concurrently created target.
            if(!link($temporary, $target)) {
                throw new RuntimeException('Cannot publish the LoRA file without overwriting an existing file: '.$target);
            }
            $published = true;
            return $save();
        } catch(Throwable $e) {
            if($published && file_exists($target)) {
                $this->rollBack($target, $temporary, $hash, $e);
            }
            throw $e;
        } finally {
            if(is_file($temporary) && !unlink($temporary)) {
                throw new RuntimeException('Could not remove temporary model file: '.$temporary);
            }
        }
    }

    /** @return list<string> Existing relative folders available for an explicit selection. */
    public function getDestinations() : array
    {
        $root = $this->rootPath();
        $folders = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach($iterator as $file) {
            if(!$file instanceof SplFileInfo) {
                throw new RuntimeException('Unexpected LoRA folder iterator entry.');
            }
            if(!$file->isDir()) {
                continue;
            }
            $resolved = $file->getRealPath();
            if($resolved === false || !self::inside($resolved, $root)) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1);
            $folders[] = $relative;
        }
        sort($folders);
        return $folders;
    }

    /** The registry's FLUX alias refers exclusively to Flux.1 Dev, not other Flux variants. */
    private static function modelFamily(string $baseModel) : ?string
    {
        return match($baseModel) {
            'FLUX', 'Flux.1 D' => 'FLUX',
            'SDXL 1.0', 'SDXL 0.9', 'SDXL Lightning', 'SDXL Hyper' => 'SDXL',
            default => null
        };
    }

    private function rollBack(string $target, string $temporary, string $hash, Throwable $error) : void
    {
        clearstatcache(true, $target);
        $temporaryStat = stat($temporary);
        $targetStat = stat($target);
        if(is_link($target) || $temporaryStat === false || $targetStat === false
            || $temporaryStat['dev'] !== $targetStat['dev'] || $temporaryStat['ino'] !== $targetStat['ino']
            || self::digest($target) !== $hash) {
            throw new RuntimeException('Registry save failed; the copied destination changed and was not removed: '.$target, 0, $error);
        }
        if(!unlink($target)) {
            throw new RuntimeException('Registry save failed and the new copy could not be removed: '.$target, 0, $error);
        }
    }

    private function sourcePath(string $input) : ?string
    {
        if(!str_contains($input, '\\') && !str_contains($input, '/') && preg_match('/^[A-Za-z]:/', $input) !== 1) {
            return null;
        }
        $path = realpath($input);
        if($path === false || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Local LoRA source file is missing or unreadable: '.$input);
        }
        return $path;
    }

    private function rootPath() : string
    {
        $root = realpath($this->root);
        if($root === false || !is_dir($root)) {
            throw new RuntimeException('ComfyUI LoRA root folder does not exist: '.$this->root);
        }
        return rtrim($root, '\\/');
    }

    private static function relativePath(string $relative) : string
    {
        $relative = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $relative);
        if($relative === '' || preg_match('/[:\\x00-\\x1f<>\"|?*]/', $relative) === 1
            || array_any(explode(DIRECTORY_SEPARATOR, $relative),
                static fn(string $part) : bool => $part === '' || $part === '.' || $part === '..')) {
            throw new RuntimeException('Destination must be a relative subfolder without traversal or drive prefixes.');
        }
        return $relative;
    }

    private function destinationPath(string $relative) : string
    {
        $root = $this->rootPath();
        $relative = self::relativePath($relative);
        $folder = realpath($root.DIRECTORY_SEPARATOR.$relative);
        if($folder === false || !is_dir($folder) || !self::inside($folder, $root)) {
            throw new RuntimeException('Destination must be an existing folder inside the ComfyUI LoRA root: '.$relative);
        }
        return $folder;
    }

    private static function inside(string $path, string $root) : bool
    {
        $prefix = $root.DIRECTORY_SEPARATOR;
        return PHP_OS_FAMILY === 'Windows'
            ? strncasecmp($path, $prefix, strlen($prefix)) === 0
            : str_starts_with($path, $prefix);
    }

    private function sameContents(string $source, string $target) : bool
    {
        $sourceSize = filesize($source);
        $targetSize = filesize($target);
        if($sourceSize === false || $targetSize === false) {
            throw new RuntimeException('Cannot determine model file sizes.');
        }
        return $sourceSize === $targetSize && self::digest($source) === self::digest($target);
    }

    private static function digest(string $path) : string
    {
        $hash = hash_file('sha256', $path);
        if($hash === false) {
            throw new RuntimeException('Cannot read model file for integrity verification: '.$path);
        }
        return $hash;
    }
}
