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

$sourcePath = $files[$imageID][ImageIndexer::INDEX_IMAGE_FILE];

// If a JPG variant of a PNG image exists, use that instead,
// because this will be the edited version. Failing that, the full
// image is the upscaled version when there is one; thumbnails stay
// based on the original.
$jpg = str_replace('.png', '.jpg', $sourcePath);
if(file_exists($jpg)) {
    $sourcePath = $jpg;
} elseif(!$request->getBool('thumbnail')) {
    $upscaled = ImageInfo::resolveUpscaledPath($sourcePath);
    if(file_exists($upscaled)) {
        $sourcePath = $upscaled;
    }
}

$sourceImage = FileInfo::factory($sourcePath);

if($request->getBool('thumbnail'))
{
    $thumbnailImage = FileInfo::factory($files[$imageID][ImageIndexer::INDEX_THUMBNAIL_FILE]);

    // Create thumbnail if it doesn't exist or if the source image is newer than the thumbnail.
    if(!$thumbnailImage->exists() || $thumbnailImage->getModifiedDate() < $sourceImage->getModifiedDate()) {
        ImageInfo::createThumbnail(
            $sourceImage,
            $thumbnailImage
        );
    }

    $imageFile = $thumbnailImage;
}
else
{
    $imageFile = $sourceImage;
}

ImageHelper::displayImage($imageFile->getPath());
