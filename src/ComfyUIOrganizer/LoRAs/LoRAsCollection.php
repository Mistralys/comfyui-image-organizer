<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\LoRAs;

use AppUtils\Collections\BaseStringPrimaryCollection;

class LoRAsCollection extends BaseStringPrimaryCollection
{
    public const string LORA_EYE_DETAILER = 'eyeDetailer';
    public const string LORA_DETAILER = 'detailer';
    public const string LORA_SKIN_TEXTURE = 'skinTexture';
    public const string LORA_BODY_WEIGHT = 'bodyWeight';
    public const string LORA_BETTER_PICTURE = 'betterPicture';
    public const string LORA_LONG_EXPOSURE = 'longExposure';
    public const string LORA_ALIEN_ZKIN = 'alienZkin';
    public const string LORA_RETRO_FUTURISM = 'retroFuturism';
    public const string LORA_RETRO_ROCKET = 'retroRocket';
    public const string LORA_CYBER_GRAPHIC = 'cyberGraphic';
    public const string LORA_NEON_CYBERPUNK = 'neonCyberpunk';
    public const string LORA_TRON_CYBERSPACE = 'tronCyberspace';
    public const string LORA_FAETASTIC = 'faetastic';
    public const string LORA_EPIC_FANTASY = 'epicFantasy';
    public const string LORA_GREG = 'greg';
    public const string LORA_FRAZETTA = 'frazetta';
    public const string LORA_CIRI = 'ciri';
    public const string LORA_YENNEFER = 'yennefer';
    public const string LORA_VAMPIRE_FANGS = 'vampireFangs';
    public const string LORA_FAIRY_DUST = 'fairyDust';
    public const string LORA_OIL_PAINTING = 'oilPainting';
    public const string LORA_WATERCOLOR = 'watercolor';
    public const string LORA_EXPRESSIONISM = 'expressionism';
    public const string LORA_SAND = 'sand';
    public const string LORA_SHINY_SURFACE = 'shinySurface';
    public const string LORA_SHINY_SURFACES = 'shinySurfaces';
    public const string LORA_IRIDESCENCE = 'iridescence';
    public const string LORA_BIO_LUMINESCENCE = 'bioLuminescence';
    public const string LORA_GLASS = 'glass';
    public const string LORA_CRYSTAL_GLASS = 'crystalGlass';
    public const string LORA_HOLOGRAPHIC = 'holographic';
    public const string LORA_OIL_PAINT = 'oilPaint';
    public const string LORA_GLOWING_HOT_METAL = 'glowingHotMetal';
    public const string LORA_WATERCOLOR_SKETCH = 'watercolorSketch';
    public const string LORA_TAPESTRIES = 'tapestries';
    public const string LORA_ELECTROLYTIC_ETCHING = 'electrolyticEtching';
    public const string LORA_NEON_NOIR = 'neonNoir';
    public const string LORA_OPALESCENCE = 'opalescence';
    public const string LORA_CYBERPUNK_CINEMATIC = 'cyberpunkCinematic';
    public const string LORA_ORGANIC_MACABRE = 'organicMacabre';
    public const string LORA_BONES = 'bones';
    public const string LORA_PAINTED_WORLD = 'paintedWorld';
    public const string LORA_CHINESE_ILLUSTRATION = 'chineseIllustration';
    public const string LORA_INK_PUNK = 'inkPunk';
    public const string LORA_PARCHMENT = 'parchment';
    public const string LORA_SCIFI_ENVIRONMENTS = 'scifiEnvironments';

    /**
     * @var string[]
     */
    public const array LORA_NAMES = array(
        self::LORA_EYE_DETAILER,
        self::LORA_DETAILER,
        self::LORA_SKIN_TEXTURE,
        self::LORA_BODY_WEIGHT,
        self::LORA_BETTER_PICTURE,
        self::LORA_LONG_EXPOSURE,
        self::LORA_ALIEN_ZKIN,
        self::LORA_RETRO_FUTURISM,
        self::LORA_RETRO_ROCKET,
        self::LORA_CYBER_GRAPHIC,
        self::LORA_NEON_CYBERPUNK,
        self::LORA_TRON_CYBERSPACE,
        self::LORA_FAETASTIC,
        self::LORA_EPIC_FANTASY,
        self::LORA_GREG,
        self::LORA_FRAZETTA,
        self::LORA_CIRI,
        self::LORA_YENNEFER,
        self::LORA_VAMPIRE_FANGS,
        self::LORA_FAIRY_DUST,
        self::LORA_OIL_PAINTING,
        self::LORA_WATERCOLOR,
        self::LORA_EXPRESSIONISM,
        self::LORA_SAND,
        self::LORA_SHINY_SURFACE,
        self::LORA_SHINY_SURFACES,
        self::LORA_IRIDESCENCE,
        self::LORA_BIO_LUMINESCENCE,
        self::LORA_GLASS,
        self::LORA_CRYSTAL_GLASS,
        self::LORA_HOLOGRAPHIC,
        self::LORA_OIL_PAINT,
        self::LORA_GLOWING_HOT_METAL,
        self::LORA_WATERCOLOR_SKETCH,
        self::LORA_TAPESTRIES,
        self::LORA_ELECTROLYTIC_ETCHING,
        self::LORA_NEON_NOIR,
        self::LORA_OPALESCENCE,
        self::LORA_CYBERPUNK_CINEMATIC,
        self::LORA_ORGANIC_MACABRE,
        self::LORA_BONES,
        self::LORA_PAINTED_WORLD,
        self::LORA_CHINESE_ILLUSTRATION,
        self::LORA_INK_PUNK,
        self::LORA_PARCHMENT,
        self::LORA_SCIFI_ENVIRONMENTS,
    );

    private static ?self $instance = null;

    public static function getInstance(): self
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

    protected function registerItems(): void
    {
        foreach(self::LORA_NAMES as $loraName) {
            $this->registerItem(new LoRA($loraName));
        }
    }
}
