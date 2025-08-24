<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

use AppUtils\ClassHelper;
use AppUtils\FileHelper\FolderInfo;
use AppUtils\FileHelper\JSONFile;
use AppUtils\FileHelper\PathInfoInterface;
use Mistralys\ComfyUIOrganizer\Pages\ImageBrowser;
use Mistralys\ComfyUIOrganizer\Pages\ImageDetails;
use Mistralys\ComfyUIOrganizer\Pages\IndexManagerPage;
use Mistralys\X4\UI\Ajax\AjaxMethodInterface;
use Mistralys\X4\UI\Ajax\AjaxMethods;
use Mistralys\X4\UI\UserInterface;
use Mistralys\X4\X4Application;
use SplFileInfo;
use function AppLocalize\t;
use const Mistralys\ComfyUIOrganizer\Config\APP_IMAGE_FOLDER;

class OrganizerApp extends X4Application
{
    private FolderInfo $imageFolder;
    private FolderInfo $storageFolder;
    private FolderInfo $cacheFolder;
    private UserInterface $ui;

    public function __construct(SplFileInfo|string|PathInfoInterface $imageFolder)
    {
        $this->imageFolder = FolderInfo::factory($imageFolder);
        $this->storageFolder = FolderInfo::factory(__DIR__.'/../../data');
        $this->cacheFolder = FolderInfo::factory(__DIR__.'/../../cache');

        parent::__construct();
    }

    private static ?OrganizerApp $instance = null;

    public static function create() : OrganizerApp
    {
        if(!isset(self::$instance)) {
            self::$instance = new OrganizerApp(APP_IMAGE_FOLDER);
        }

        return self::$instance;
    }

    public function getCacheFolder() : FolderInfo
    {
        return $this->cacheFolder;
    }

    public function getImageFolder() : FolderInfo
    {
        return $this->imageFolder;
    }

    public function getStorageFolder(): FolderInfo
    {
        return $this->storageFolder;
    }

    public function getStorageFile() : JSONFile
    {
        return JSONFile::factory($this->getStorageFolder().'/images.json')
            ->setPrettyPrint(true)
            ->setTrailingNewline(true);
    }

    private ?ImageCollection $imageCollection = null;

    public function createImageCollection() : ImageCollection
    {
        if(!isset($this->imageCollection)) {
            $this->imageCollection = new ImageCollection($this->getStorageFile());
        }

        return $this->imageCollection;
    }

    public function getTitle(): string
    {
        return t('ComfyUI Organizer');
    }

    public function registerPages(UserInterface $ui): void
    {
        $ui->registerPage(ImageBrowser::URL_NAME, ImageBrowser::class);
        $ui->registerPage(ImageDetails::URL_NAME, ImageDetails::class);
        $ui->registerPage(IndexManagerPage::URL_NAME, IndexManagerPage::class);
    }

    public function registerAjaxMethods(AjaxMethods $methods): void
    {
        $ajaxClasses = ClassHelper::findClassesInRepository(
            FolderInfo::factory(__DIR__.'/Ajax/Methods'),
            false,
            AjaxMethodInterface::class
        );

        foreach($ajaxClasses->getClasses() as $class) {
            $methods->addItem(new $class($methods));
        }
    }

    public function getDefaultPageID(): ?string
    {
        return ImageBrowser::URL_NAME;
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function url() : URLs
    {
        return new URLs($this->getUI());
    }
}
