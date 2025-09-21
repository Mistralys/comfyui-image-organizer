<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Pages;

use Mistralys\ComfyUIOrganizer\BaseOrganizerPage;
use Mistralys\ComfyUIOrganizer\LoRAs\LoRA;
use Mistralys\ComfyUIOrganizer\LoRAs\LoRAsCollection;
use function AppLocalize\t;

class LoRAOverviewPage extends BaseOrganizerPage
{
    public const string URL_NAME = 'loras';

    public function getID(): string
    {
        return self::URL_NAME;
    }

    public function getTitle(): string
    {
        return t('LoRA Overview');
    }

    public function getSubtitle(): string
    {
        return '';
    }

    public function getAbstract(): string
    {
        return t('This is a list of all LoRAs found in your ComfyUI setup.');
    }

    public function getNavTitle(): string
    {
        return t('LoRAs');
    }

    protected function preRender(): void
    {
        $this->loadCategoryFilter();
    }

    /**
     * @var string[]
     */
    private array $categoryFilter = array();

    private function loadCategoryFilter() : void
    {
        $categories = $this->request->registerParam('category_filter')->setArray()->get();

        if(empty($categories) || !is_array($categories)) {
            return;
        }

        $knownCategories = LoRAsCollection::getInstance()->getCategories();
        $selected = array_filter($categories, function ($cat) use ($knownCategories) {
            return in_array($cat, $knownCategories, true);
        });

        if(!empty($selected)) {
            $this->categoryFilter = $selected;
        }
    }

    protected function _render(): void
    {
        $categories = LoRAsCollection::getInstance()->getCategories();

        ?>
        <form method="post">
            <select name="category_filter[]" multiple="multiple" size="<?php echo count($categories) ?>">
                <?php

                foreach($categories as $category)
                {
                    $selected = '';
                    if(!empty($this->categoryFilter) && in_array($category, $this->categoryFilter, true)) {
                        $selected = ' selected="selected"';
                    }

                    ?>
                    <option value="<?php echo htmlspecialchars($category) ?>" <?php echo $selected ?>>
                        <?php echo htmlspecialchars($category) ?>
                    </option>
                    <?php
                }
                ?>
            </select>
            <button type="submit" class="btn btn-primary"><?php echo t('Filter'); ?></button>
        </form>
        <?php

        $grid = $this->ui->createDataGrid();

        $grid->addColumn('id', t('ID'))
            ->useObjectValues()
            ->fetchByMethod(array(LoRA::class, 'getID'));

        $grid->addColumn('label', t('Label'))
            ->useObjectValues()
            ->fetchByMethod(array(LoRA::class, 'getLabelLinkedSource'));

        $grid->addColumn('category', t('Category'))
            ->useObjectValues()
            ->fetchByMethod(array(LoRA::class, 'getCategory'));

        $grid->addColumn('triggerWords', t('Trigger Words'))
            ->useObjectValues()
            ->fetchByMethod(array(LoRA::class, 'getTriggerWordsFlattened'));

        $grid->addRowsFromObjects($this->filterResults());

        echo $grid;
    }

    /**
     * @return LoRA[]
     */
    private function filterResults() : array
    {
        $items = LoRAsCollection::getInstance()->getAll();

        if(!empty($this->categoryFilter)) {
            $categories = $this->categoryFilter;
            $items = array_filter(
                $items,
                function(LoRA $lora) use ($categories) {
                    return in_array($lora->getCategory(), $categories, true);
                }
            );
        }

        return $items;
    }

    protected function getURLParams(): array
    {
        return array();
    }
}
