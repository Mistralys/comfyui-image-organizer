<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Pages;

use AppUtils\ArrayDataCollection;
use AppUtils\FileHelper\FileInfo;
use AppUtils\OutputBuffering;
use Mistralys\ComfyUIOrganizer\Ajax\Methods\SendToWebsiteMethod;
use Mistralys\ComfyUIOrganizer\ImageInfo;
use Mistralys\ComfyUIOrganizer\ImageProperties;
use Mistralys\ComfyUIOrganizer\LoRAs\LoRAsCollection;
use Mistralys\ComfyUIOrganizer\OrganizerApp;
use Mistralys\X4\UI\Ajax\AjaxMethods;
use Mistralys\X4\UI\Icon;
use Mistralys\X4\UI\Page\BasePage;
use function AppLocalize\pt;
use function AppLocalize\pts;
use function AppUtils\sb;
use function AppUtils\t;
use const Mistralys\ComfyUIOrganizer\Config\APP_WEBROOT_URL;

class ImageDetails extends BasePage
{
    public const string URL_NAME = 'image-details';

    public const string REQUEST_PARAM_RESET_WSIMGID = 'reset-website-image';
    public const string REQUEST_PARAM_SEND_TO_WEBSITE = 'send-to-website';

    private ?ImageInfo $image;

    public function getID(): string
    {
        return self::URL_NAME;
    }

    public function getTitle(): string
    {
        return '🔎 '.$this->image->getLabel();
    }

    public function getSubtitle(): string
    {
        return '';
    }

    public function getAbstract(): string
    {
        return '';
    }

    public function getNavTitle(): string
    {
        return t('Details');
    }

    protected function preRender(): void
    {
        $this->getUI()->addInternalStylesheet('app.css');

        $image = OrganizerApp::create()->createImageCollection()->getFromRequest();

        if($image === null) {
            $this->redirect(APP_WEBROOT_URL);
        }

        $this->image = $image;

        if($this->request->getBool(self::REQUEST_PARAM_RESET_WSIMGID)) {
            $this->handleResetWebsiteImageID();
        }

        if($this->request->getBool(self::REQUEST_PARAM_SEND_TO_WEBSITE)) {
            $this->handleSendToWebsite();
        }
    }

    private function handleResetWebsiteImageID() : never
    {
        $this->image->prop()->setWebsiteImageID('');
        $this->image->save();

        $this->redirectWithSuccessMessage(
            $this->image->getViewDetailsURL(),
            t('The website image ID has been reset.')
        );
    }

    private function handleSendToWebsite() : never
    {
        $method = new SendToWebsiteMethod(new AjaxMethods(OrganizerApp::create()->getUI()));
        $method->sendRequest($this->image);

        $this->redirectWithSuccessMessage(
            $this->image->getViewDetailsURL(),
            t('The image has been successfully sent to the website.')
        );
    }

    protected function _render(): void
    {
        OutputBuffering::start();

        ?>
        <p>
            <?php echo $this->image->getUpscalingBadge() ?>
        </p>
        <div id="wrapper-<?php echo $this->image->getID() ?>">
            <p>
                <a href="<?php echo $this->image->getURL() ?>" target="_blank">
                    <img class="detail-image" src="<?php echo $this->image->getURL() ?>" alt="<?php echo t('Full image preview') ?>">
                </a>
            </p>
            <p>
                <i>
                <?php
                pts('Hint:');
                pts('The image can be dragged from here into ComfyUI to load its workflow.');
                ?>
                </i>
            </p>
        </div>
        <?php

        $loraIDs = LoRAsCollection::getInstance()->getIDs();
        $serialized = ArrayDataCollection::create($this->image->prop()->serialize());

        $loras = array();
        $props = array();
        foreach($serialized->getData() as $key => $value)
        {
            if(in_array($key, $loraIDs)) {
                $loras[t('LoRA: ').$key] = $value;
                continue;
            }

            $props[$key] = $value;
        }

        ksort($loras);

        $this->renderProperties(
            t('Image Properties'),
            array(
                t('ID') => sb()->spanned($this->image->getID(), 'mono'),
                t('Date') => $this->image->getDate()->format('Y-m-d H:i:s'),
                t('Batch number') => $this->image->prop()->getBatchNumber(),
                t('Upscaled?') => $this->renderBool($this->image->isUpscaled()),
                t('Favorite?') => $this->renderBool($this->image->prop()->isFavorite()),
                t('For gallery?') => $this->renderBool($this->image->prop()->isForGallery()),
                t('For website?') => $this->renderBool($this->image->prop()->isForWebsite()),
                t('Test name') => $this->image->prop()->getTestName().' #'.$this->image->prop()->getTestNumber()
            )
        );

        $this->renderProperties(
            t('Generation Properties'),
            array(
                t('Checkpoint') => $this->image->getCheckpoint(),
                t('Image size') => $this->image->getImageSize()['width'].' x '.$this->image->getImageSize()['height'],
                t('LoRA summary') => $this->image->prop()->getLoRASummary(),
                t('Sampler') => $this->image->prop()->getSampler().' / '.$this->image->prop()->getScheduler(),
                t('Steps') => $this->image->prop()->getSamplerSteps(),
                t('Guidance Scale') => $this->image->prop()->getCFG(),
                t('Seed') => $this->image->prop()->getSeed(),
                t('Prompt') => $this->image->prop()->getPromptPositive(),
                t('Negative Prompt') => $this->image->prop()->getPromptNegative()
            )
        );

        $this->renderProperties('LoRAs', $loras);

        $this->renderProperties(
            t('Files'),
            array(
                t('Folder') => $serialized->getString(ImageProperties::KEY_FOLDER_NAME),
                t('Image file') => $this->adjustPath($this->image->getImageFile()),
                t('Sidecar file') => $this->adjustPath($this->image->getSidecarFile()),
                t('PSD File') => $this->renderOptionalFile($this->image->getPSDFile()),
                t('JPG File') => $this->renderOptionalFile($this->image->getJPGFile()),
            )
        );

        $imageID = $this->image->prop()->getWebsiteImageID();
        if(!empty($imageID)) {
            $imageID = '<span class="mono">#'.$imageID.'</span> &#160; <a href="'.$this->image->getViewDetailsURL(array(self::REQUEST_PARAM_RESET_WSIMGID => 'yes')).'" target="_blank">('.t('Reset').')</a>';
        } else {
            $imageID = '<span class="text-secondary">('.t('Empty').')</span>';
        }

        $this->renderProperties(
            t('Website Gallery connection'),
            array(
                t('Website Image') => $this->renderOptionalFile($this->image->getWebsiteImage()),
                t('Website image ID') => $imageID
            )
        );

        if($this->image->getWebsiteImage()->exists() && empty($this->image->prop()->getWebsiteImageID()) ) {
            ?>
            <a href="<?php echo $this->image->getViewDetailsURL(array(self::REQUEST_PARAM_SEND_TO_WEBSITE => 'yes')) ?>" class="btn btn-primary">
                <?php echo Icon::typeSolid('cloud-upload-alt') ?>
                <?php pt('Send to website') ?>
            </a>
            <?php
        }

        OutputBuffering::flush();
    }

    private function renderProperties(string $title, array $properties) : void
    {
        $grid = $this->ui->createDataGrid();
        $grid->addColumn('key', t('Property'))->addClass('property-column');
        $grid->addColumn('value', t('Value'));

        foreach($properties as $key => $value)
        {
            if(is_bool($value)) {
                $value = $this->renderBool($value);
            }

            $grid->addRowFromArray(array(
                'key' => $key,
                'value' => $value
            ));
        }

        echo '<h3>'.$title.'</h3>'.$grid;
    }

    private function renderOptionalFile(FileInfo $file) : string
    {
        if($file->exists()) {
            return $this->adjustPath($file);
        }

        return '<span class="text-secondary">('.t('File not present:').' '.$file->getName().')</span>';

    }

    private function adjustPath(FileInfo $file) : string
    {
        if(PHP_OS_FAMILY === 'Windows') {
            return str_replace('/', '\\', $file->getPath());
        }

        return $file->getPath();
    }

    public function getNavItems(): array
    {
        return array();
    }

    protected function getURLParams(): array
    {
        return array();
    }
}
