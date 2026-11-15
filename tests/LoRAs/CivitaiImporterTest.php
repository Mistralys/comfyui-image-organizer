<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests\LoRAs;

use Mistralys\ComfyUIOrganizer\LoRAs\CivitaiImporter;
use Mistralys\ComfyUIOrganizer\LoRAs\LoRA;
use Mistralys\ComfyUIOrganizer\LoRAs\LoRAFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CivitaiImporterTest extends TestCase
{
    private string $folder;
    private string $registry;
    private const string URL = 'https://civitai.com/models/832858/anime-art?modelVersionId=1376386';

    protected function setUp() : void
    {
        $this->folder = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lora-import-'.bin2hex(random_bytes(8));
        mkdir($this->folder);
        $this->registry = $this->folder.DIRECTORY_SEPARATOR.'loras.json';
        file_put_contents($this->registry, "{\r\n  \"existing\": {\"label\":\"Existing\",\"category\":\"Styles\",\"extra\":{\"empty\":{},\"list\":[]},\"models\":{}}\r\n}\r\n");
    }

    protected function tearDown() : void
    {
        foreach(scandir($this->folder) ?: array() as $name) {
            if($name !== '.' && $name !== '..') {
                unlink($this->folder.DIRECTORY_SEPARATOR.$name);
            }
        }
        rmdir($this->folder);
    }

    /** @return array<string,mixed> */
    private static function version() : array
    {
        return array(
            'id' => 1376386,
            'modelId' => 832858,
            'baseModel' => 'Flux.1 D',
            'trainedWords' => array('Anime art'),
            'model' => array('name' => 'Anime art', 'type' => 'LORA')
        );
    }

    /** @param array<string,mixed>|null $response */
    private function importer(?array $response = null) : CivitaiImporter
    {
        $json = json_encode($response ?? self::version(), JSON_THROW_ON_ERROR);
        return new CivitaiImporter($this->folder, static fn(string $url) : string => $json);
    }

    public function testExactVersionImportKeepsFilenameAndExistingJSON() : void
    {
        $original = file_get_contents($this->registry);
        $this->assertIsString($original);
        $importer = new CivitaiImporter($this->folder, function(string $url) : string {
            $this->assertSame('https://civitai.com/api/v1/model-versions/1376386', $url);
            $version = self::version();
            $version['description'] = 'The author also discusses ZImageTurbo builds here.';
            return json_encode($version, JSON_THROW_ON_ERROR);
        });
        $lora = $importer->import(self::URL, 'C:\\Downloads\\Anime Art V3.safetensors', 'Art');

        $this->assertSame('animeArt', $lora->getID());
        $this->assertSame(array('Anime Art V3.safetensors'), $lora->getFiles());
        $this->assertSame(array('FLUX'), $lora->getModelNames());
        $this->assertSame(array('Anime art'), $lora->getTriggerWords());
        $this->assertSame('https://civitai.com/models/832858?modelVersionId=1376386', $lora->getSource());
        $json = file_get_contents($this->registry);
        $this->assertIsString($json);
        $this->assertStringStartsWith(rtrim(substr($original, 0, (int)strrpos($original, '}'))).',', $json);
        $this->assertStringContainsString('"empty":{},"list":[]', $json);
        $entries = LoRAFile::load($this->registry)->getEntries();
        $this->assertCount(2, $entries);
        $this->assertSame($lora->getFiles(), $entries[1]->getFiles());
        $this->assertSame(array('Art', 'Styles'), $importer->getCategories());
    }

    public function testLatestVersionIsResolvedAndPinnedWithoutWritingInDryRun() : void
    {
        $original = file_get_contents($this->registry);
        $calls = array();
        $importer = new CivitaiImporter($this->folder, static function(string $url) use (&$calls) : string {
            $calls[] = $url;
            return json_encode(str_contains($url, '/models/') ? array(
                'id' => 832858, 'modelVersions' => array(array('id' => 1376386))
            ) : self::version(), JSON_THROW_ON_ERROR);
        });

        $lora = $importer->import('https://civitai.com/models/832858/anime-art', 'Anime Art V3.safetensors', 'Styles', true);
        $this->assertSame(array(
            'https://civitai.com/api/v1/models/832858',
            'https://civitai.com/api/v1/model-versions/1376386'
        ), $calls);
        $this->assertStringEndsWith('?modelVersionId=1376386', $lora->getSource());
        $this->assertSame($original, file_get_contents($this->registry));
    }

    public function testCategoriesIncludeOtherFilesAndAreSortedAndUnique() : void
    {
        file_put_contents($this->folder.DIRECTORY_SEPARATOR.'other.json', '{"a":{"category":"Art"},"b":{"category":"Styles"},"c":{}}');
        $this->assertSame(array('Art', 'Styles'), $this->importer()->getCategories());
    }

    public function testCustomEntryMetadataIsPreviewedAndPersisted() : void
    {
        $original = file_get_contents($this->registry);
        $description = "Pastel anime style.\nSuggested strength: 0.8; keep \"soft\" colors.";
        $importer = $this->importer();
        $preview = $importer->import(self::URL, 'Anime Art V3.safetensors', 'Styles', true,
            id: 'myAnimeV3', label: 'My Anime Art', description: $description);
        $this->assertSame('myAnimeV3', $preview->getID());
        $this->assertSame('My Anime Art', $preview->getLabel());
        $this->assertSame($description, $preview->getDescription());
        $this->assertSame($original, file_get_contents($this->registry));

        $importer->import(self::URL, 'Anime Art V3.safetensors', 'Styles',
            id: 'myAnimeV3', label: 'My Anime Art', description: $description);
        $entries = LoRAFile::load($this->registry)->getEntries();
        $this->assertCount(2, $entries);
        $this->assertSame($preview->getID(), $entries[1]->getID());
        $this->assertSame($preview->getLabel(), $entries[1]->getLabel());
        $this->assertSame($description, $entries[1]->getDescription());
    }

    public function testCustomLabelDoesNotChangeGeneratedID() : void
    {
        $lora = $this->importer()->import(self::URL, 'Anime Art V3.safetensors', 'Styles',
            label: 'A completely different label', description: 'Custom notes');
        $this->assertSame('animeArt', $lora->getID());
        $this->assertSame('A completely different label', $lora->getLabel());
        $this->assertSame('Custom notes', LoRAFile::load($this->registry)->getEntries()[1]->getDescription());
    }

    public function testCustomIDPreservesDefaultAPILabel() : void
    {
        $lora = $this->importer()->import(self::URL, 'Anime Art V3.safetensors', 'Styles', id: 'customAnime');
        $this->assertSame('customAnime', $lora->getID());
        $this->assertSame('Anime art', $lora->getLabel());
    }

    /** @return array<string,array{?string,?string}> */
    public static function invalidOverridesProvider() : array
    {
        return array(
            'empty ID' => array('', null),
            'ID starts with number' => array('3anime', null),
            'ID punctuation' => array('anime-art', null),
            'empty label' => array(null, ''),
            'whitespace label' => array(null, '   ')
        );
    }

    #[DataProvider('invalidOverridesProvider')]
    public function testInvalidOverridesFailBeforeNetwork(?string $id, ?string $label) : void
    {
        $importer = new CivitaiImporter($this->folder, function(string $url) : string {
            $this->fail('Invalid overrides must fail before an API request.');
        });
        $this->expectException(RuntimeException::class);
        $importer->import(self::URL, 'Anime Art V3.safetensors', 'Styles', id: $id, label: $label);
    }

    public function testExplicitIDConflictDoesNotInvokeRenamePrompt() : void
    {
        $existing = '{"customAnime":{"models":{"FLUX":{"file":"other.safetensors"}}}}';
        file_put_contents($this->registry, $existing);
        try {
            $this->importer()->import(self::URL, 'Anime Art V3.safetensors', 'Styles',
                resolveID: function() : never {
                    $this->fail('Explicit IDs must not be changed by a rename prompt.');
                },
                id: 'customAnime');
            $this->fail('A conflicting explicit ID must fail.');
        } catch(RuntimeException $e) {
            $this->assertStringContainsString('different ID is required', $e->getMessage());
            $this->assertSame($existing, file_get_contents($this->registry));
        }
    }

    /** @return array<string,array{?string,?string}> */
    public static function conflictingMergeMetadataProvider() : array
    {
        return array(
            'different label' => array('New label', null),
            'different description' => array(null, 'New notes')
        );
    }

    #[DataProvider('conflictingMergeMetadataProvider')]
    public function testMergeRejectsConflictingEntryOverrides(?string $label, ?string $description) : void
    {
        $existing = '{"customAnime":{"label":"Keep label","description":"Keep notes","models":{"SDXL 1.0":{"file":"sdxl.safetensors"}}}}';
        file_put_contents($this->registry, $existing);
        try {
            $this->importer()->import(self::URL, 'Anime Art V3.safetensors', 'Styles',
                id: 'customAnime', label: $label, description: $description);
            $this->fail('A merge must not silently overwrite or ignore conflicting entry overrides.');
        } catch(RuntimeException $e) {
            $this->assertStringContainsString('different entry metadata', $e->getMessage());
            $this->assertSame($existing, file_get_contents($this->registry));
        }
    }

    /** @return array<string,array{string,string,string}> */
    public static function invalidInputProvider() : array
    {
        return array(
            'foreign host' => array('https://example.com/models/832858', 'x.safetensors', 'Styles'),
            'spoofed host' => array('https://civitai.com.example.com/models/832858', 'x.safetensors', 'Styles'),
            'array version' => array(self::URL.'&modelVersionId[]=1', 'x.safetensors', 'Styles'),
            'bad version' => array('https://civitai.com/models/832858?modelVersionId=bad', 'x.safetensors', 'Styles'),
            'empty version' => array('https://civitai.com/models/832858?modelVersionId=', 'x.safetensors', 'Styles'),
            'wrong extension' => array(self::URL, 'x.txt', 'Styles'),
            'empty category' => array(self::URL, 'x.safetensors', ' ')
        );
    }

    #[DataProvider('invalidInputProvider')]
    public function testInvalidInputFailsBeforeNetwork(string $url, string $filename, string $category) : void
    {
        $importer = new CivitaiImporter($this->folder, function(string $url) : string {
            $this->fail('Invalid input must not make a network request.');
        });
        $this->expectException(RuntimeException::class);
        $importer->import($url, $filename, $category);
    }

    /** @return array<string,array{array<string,mixed>}> */
    public static function invalidMetadataProvider() : array
    {
        $version = self::version();
        return array(
            'wrong model' => array(array_replace($version, array('modelId' => 1))),
            'wrong version' => array(array_replace($version, array('id' => 1))),
            'not a lora' => array(array_replace($version, array('model' => array('name' => 'Checkpoint', 'type' => 'Checkpoint')))),
            'missing base model' => array(array_diff_key($version, array('baseModel' => true))),
            'invalid words' => array(array_replace($version, array('trainedWords' => array(1))))
        );
    }

    /** @param array<string,mixed> $response */
    #[DataProvider('invalidMetadataProvider')]
    public function testInvalidMetadataLeavesRegistryUntouched(array $response) : void
    {
        $original = file_get_contents($this->registry);
        try {
            $this->importer($response)->import(self::URL, 'Anime Art V3.safetensors', 'Styles');
            $this->fail('Invalid metadata must fail.');
        } catch(RuntimeException) {
            $this->assertSame($original, file_get_contents($this->registry));
        }
    }

    /** @return array<string,array{string}> */
    public static function duplicateProvider() : array
    {
        return array(
            'same base model' => array('{"animeArt":{"models":{"FLUX":{"file":"other.safetensors"}}}}'),
            'flat filename' => array('{"other":{"file":"ANIME ART V3.safetensors"}}'),
            'nested filename' => array('{"other":{"models":{"FLUX":{"file":"Anime Art V3.safetensors"}}}}'),
            'mirror version URL' => array('{"other":{"source":"https://civitai.red/models/832858/slug?modelVersionId=1376386"}}')
        );
    }

    #[DataProvider('duplicateProvider')]
    public function testDuplicatesInOtherFilesAreRejected(string $json) : void
    {
        file_put_contents($this->folder.DIRECTORY_SEPARATOR.'other.json', $json);
        $original = file_get_contents($this->registry);
        try {
            $this->importer()->import(self::URL, 'Anime Art V3.safetensors', 'Styles');
            $this->fail('Duplicate must fail.');
        } catch(RuntimeException) {
            $this->assertSame($original, file_get_contents($this->registry));
        }
    }

    public function testRegistryChangedAfterLoadIsNotOverwritten() : void
    {
        $file = LoRAFile::load($this->registry);
        $lora = $this->importer()->import(self::URL, 'Anime Art V3.safetensors', 'Styles', true);
        file_put_contents($this->registry, '{}');
        try {
            $file->append($lora);
            $this->fail('Stale snapshot must fail.');
        } catch(RuntimeException) {
            $this->assertSame('{}', file_get_contents($this->registry));
            $this->assertCount(3, scandir($this->folder) ?: array());
        }
    }

    public function testDifferentBaseModelMergesWithoutIDOrCategoryPrompt() : void
    {
        $other = $this->folder.DIRECTORY_SEPARATOR.'other.json';
        file_put_contents($other, '{"animeArt":{"label":"My label","category":"Art","description":"My notes","extra":{},"models":{"SDXL 1.0":{"file":"sdxl.safetensors","unknown":{"empty":{}}}}}}');
        $failPrompt = function() : never {
            $this->fail('A different base model must merge without a prompt.');
        };
        $lora = $this->importer()->import(self::URL, 'Anime Art V3.safetensors', '', false, $failPrompt, $failPrompt);

        $this->assertSame('animeArt', $lora->getID());
        $this->assertSame('My label', $lora->getLabel());
        $this->assertSame('Art', $lora->getCategory());
        $this->assertSame('My notes', $lora->getDescription());
        $this->assertSame(array('SDXL 1.0', 'FLUX'), $lora->getModelNames());
        $this->assertCount(1, LoRAFile::load($this->registry)->getEntries());
        $stored = file_get_contents($other);
        $this->assertIsString($stored);
        $this->assertStringContainsString('"extra": {}', $stored);
        $this->assertStringContainsString('"unknown": {', $stored);
        $this->assertStringContainsString('"empty": {}', $stored);
    }

    public function testSameBaseModelRequestsAlternativeIDThenCategory() : void
    {
        file_put_contents($this->registry, '{"animeArt":{"category":"Styles","models":{"FLUX":{"file":"other.safetensors"}}}}');
        $idPrompts = 0;
        $categoryPrompts = 0;
        $lora = $this->importer()->import(self::URL, 'Anime Art V3.safetensors', '', false,
            function(LoRA $existing, LoRA $incoming) use (&$idPrompts) : string {
                ++$idPrompts;
                $this->assertSame('animeArt', $existing->getID());
                $this->assertSame(array('FLUX'), $incoming->getModelNames());
                return 'animeArtV3';
            },
            static function() use (&$categoryPrompts) : string {
                ++$categoryPrompts;
                return 'Styles';
            },
            description: 'Keep notes when resolving an ID conflict'
        );
        $this->assertSame(1, $idPrompts);
        $this->assertSame(1, $categoryPrompts);
        $this->assertSame('animeArtV3', $lora->getID());
        $entries = LoRAFile::load($this->registry)->getEntries();
        $this->assertCount(2, $entries);
        $this->assertSame(array('other.safetensors'), $entries[0]->getFiles());
        $this->assertSame(array('Anime Art V3.safetensors'), $entries[1]->getFiles());
        $this->assertSame('Keep notes when resolving an ID conflict', $entries[1]->getDescription());
    }

    public function testFlatVariantMergeKeepsModelFieldsAndUnknownEntryValues() : void
    {
        file_put_contents($this->registry, '{"animeArt":{"category":"Styles","model":"SDXL 1.0","file":"sdxl.safetensors","source":"https://example.com","notes":"Original notes","weight":"0.8","custom":{}}}');
        $this->importer()->import(self::URL, 'Anime Art V3.safetensors', 'Ignored on merge');
        $entries = LoRAFile::load($this->registry)->getEntries();
        $this->assertSame(array('SDXL 1.0', 'FLUX'), $entries[0]->getModelNames());
        $this->assertSame('Original notes', $entries[0]->getModels()[0]->getNotes());
        $this->assertSame('0.8', $entries[0]->getModels()[0]->getWeight());
        $this->assertSame('Styles', $entries[0]->getCategory());
        $stored = file_get_contents($this->registry);
        $this->assertIsString($stored);
        $this->assertStringContainsString('"custom": {}', $stored);
    }

    public function testDryRunDoesNotWriteMergedVariant() : void
    {
        $original = '{"animeArt":{"models":{"SDXL 1.0":{"file":"sdxl.safetensors"}}}}';
        file_put_contents($this->registry, $original);
        $lora = $this->importer()->import(self::URL, 'Anime Art V3.safetensors', 'Styles', true);
        $this->assertSame(array('SDXL 1.0', 'FLUX'), $lora->getModelNames());
        $this->assertSame($original, file_get_contents($this->registry));
    }

    public function testEmptyModelsArrayAcceptsFirstVariant() : void
    {
        file_put_contents($this->registry, '{"animeArt":{"label":"Anime art","models":[]}}');
        $this->importer()->import(self::URL, 'Anime Art V3.safetensors', 'Styles');
        $entries = LoRAFile::load($this->registry)->getEntries();
        $this->assertSame(array('FLUX'), $entries[0]->getModelNames());
    }

    public function testEmptyRegistryAndEmptyTriggerWordsAreSupported() : void
    {
        file_put_contents($this->registry, '{}');
        $version = self::version();
        $version['trainedWords'] = array();
        $this->importer($version)->import(self::URL, 'Anime Art V3.safetensors', 'New category');
        $entries = LoRAFile::load($this->registry)->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(array(), $entries[0]->getTriggerWords());
    }

    public function testMalformedRegistryFailsWithoutWriting() : void
    {
        file_put_contents($this->folder.DIRECTORY_SEPARATOR.'other.json', '[]');
        $this->expectException(RuntimeException::class);
        $this->importer()->import(self::URL, 'Anime Art V3.safetensors', 'Styles');
    }

    public function testTransportFailureIsNotTurnedIntoAnEntry() : void
    {
        $original = file_get_contents($this->registry);
        $importer = new CivitaiImporter($this->folder, static function(string $url) : string {
            throw new RuntimeException('Civitai returned HTTP 403.');
        });
        try {
            $importer->import(self::URL, 'Anime Art V3.safetensors', 'Styles');
            $this->fail('Transport error must fail.');
        } catch(RuntimeException $e) {
            $this->assertStringContainsString('HTTP 403', $e->getMessage());
            $this->assertSame($original, file_get_contents($this->registry));
        }
    }
}
