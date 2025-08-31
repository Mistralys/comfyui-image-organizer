<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

require_once __DIR__.'/prepend.php';

use AppUtils\FileHelper\FileInfo;
use AppUtils\ImageHelper;
use AppUtils\Request;

$request = Request::getInstance();
$imageID = $request->registerParam('imageID')->setMD5()->get();

if(empty($imageID)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$files = OrganizerApp::create()->getFileIndexFile()->getData();

if(!isset($files[$imageID])) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

if($request->getBool('thumbnail'))
{
    $imageFile = FileInfo::factory($files[$imageID][ImageIndexer::INDEX_THUMBNAIL_FILE]);
    if(!$imageFile->exists()) {
        $this->createThumbnail($imageFile);
    }
} else {
    $imageFile = FileInfo::factory($files[$imageID][ImageIndexer::INDEX_IMAGE_FILE]);
}

ImageHelper::displayImage($imageFile->getPath());
