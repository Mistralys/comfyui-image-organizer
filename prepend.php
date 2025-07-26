<?php

declare(strict_types=1);

use AppUtils\ClassHelper;
use AppUtils\FileHelper\FolderInfo;
use AppUtils\Request;
use const Mistralys\ComfyUIOrganizer\Config\APP_WEBROOT_URL;

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/config.php';

Request::getInstance()->setBaseURL(APP_WEBROOT_URL);

ClassHelper::setCacheFolder(FolderInfo::factory(__DIR__.'/cache')->create());
