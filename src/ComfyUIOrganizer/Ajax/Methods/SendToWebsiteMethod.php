<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Ajax\Methods;

use AppUtils\FileHelper_Exception;
use Mistralys\ComfyUIOrganizer\Ajax\BaseImageMethod;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\OrganizerApp;
use Mistralys\X4\UI\Ajax\AjaxMethodException;
use const Mistralys\ComfyUIOrganizer\Config\APP_WEBSITE_API_URL;

/**
 * @property OrganizerApp $application
 */
class SendToWebsiteMethod extends BaseImageMethod
{
    public const string METHOD_NAME = 'SendToWebsite';

    public const int ERROR_IMAGE_ALREADY_EXISTS = 182301;
    public const int ERROR_WEBSITE_IMAGE_NOT_FOUND = 182302;

    public const string REQUEST_KEY_IMAGE = 'image';
    public const string REQUEST_KEY_PROPERTIES = 'properties';
    public const string REQUEST_KEY_IMAGE_FILENAME = 'filename';
    public const string REQUEST_KEY_GROUP = 'group';
    public const string REQUEST_KEY_GALLERY_ID = 'galleryID';
    public const string RESPONSE_KEY_IMAGE_ID = 'imageID';

    public function getID(): string
    {
        return self::METHOD_NAME;
    }

    protected function preProcessImage(ImageInfo $image): void
    {
        if($image->prop()->getWebsiteImageID() !== null) {
            $this->sendError(
                'This image has already been copied to the website.',
                self::ERROR_IMAGE_ALREADY_EXISTS
            );
        }

        if(!$image->getWebsiteImage()->exists()) {
            $this->sendError(
                'No website image file has been created for the image.',
                self::ERROR_WEBSITE_IMAGE_NOT_FOUND
            );
        }
    }

    protected function processImage(ImageInfo $image): never
    {
        try {
            $this->sendRequest($image);
            $this->sendSuccess('Image successfully sent to the website gallery.');
        } catch(AjaxMethodException $e) {
            $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param ImageInfo $image
     * @return void
     * @throws AjaxMethodException
     * @throws FileHelper_Exception
     */
    public function sendRequest(ImageInfo $image) : void
    {
        $payload = array(
            self::REQUEST_KEY_GALLERY_ID => 'ai-art',
            self::REQUEST_KEY_IMAGE => base64_encode($image->getWebsiteImage()->getContents()),
            self::REQUEST_KEY_IMAGE_FILENAME => $image->getOutputFileName(),
            self::REQUEST_KEY_GROUP => $image->getLabel(),
            self::REQUEST_KEY_PROPERTIES => $this->compileProperties($image)
        );

        $serviceURL = rtrim(APP_WEBSITE_API_URL, '/').'/?method=AddGalleryImage';

        $responseData = $this->sendJSONViaPost($serviceURL, $payload);

        $image->prop()->setWebsiteImageID($responseData->getString(self::RESPONSE_KEY_IMAGE_ID));
        $image->save();
    }

    private function compileProperties(ImageInfo $image) : array
    {
        return array(
            'Checkpoint' => $image->getCheckpoint(),
            'Sampler' => $image->prop()->getSampler().' / '.$image->prop()->getScheduler(),
            'Steps' => $image->prop()->getSamplerSteps(),
            'CFG' => $image->prop()->getCFG(),
            'Seed' => $image->prop()->getSeed(),
            'LoRAs' => $image->prop()->getLoRASummary(),
            'Prompt' => $image->prop()->getPromptPositive()
        );
    }
}
