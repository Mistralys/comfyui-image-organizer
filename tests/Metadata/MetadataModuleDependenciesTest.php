<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests\Metadata;

use Mistralys\ComfyUIOrganizer\Builder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Guards the boundary of the `Metadata/` module: it must stay usable without the
 * indexer, the browser or `ImageInfo`.
 */
#[Group('metadata')]
final class MetadataModuleDependenciesTest extends TestCase
{
    private const string MODULE_FOLDER = __DIR__.'/../../src/ComfyUIOrganizer/Metadata';

    private const array ALLOWED_PREFIXES = array(
        'AppUtils\\',
        'Mistralys\\ComfyUIOrganizer\\Metadata\\',
    );

    private const array ALLOWED_CLASSES = array(
        'JsonException',
        'Mistralys\\ComfyUIOrganizer\\OrganizerException',
        'Mistralys\\ComfyUIOrganizer\\ImageProperties',
    );

    /**
     * @return string[]
     */
    private static function moduleFiles() : array
    {
        $files = array();

        foreach(array(self::MODULE_FOLDER.'/*.php', self::MODULE_FOLDER.'/Formats/*.php') as $pattern) {
            foreach((array)glob($pattern) as $file) {
                $files[] = (string)$file;
            }
        }

        return $files;
    }

    public function testModuleImportsOnlyAllowedNamespaces() : void
    {
        $files = self::moduleFiles();

        $this->assertNotEmpty($files);

        foreach($files as $file)
        {
            preg_match_all('/^use\s+(?:function\s+|const\s+)?([^;\s]+)/m', (string)file_get_contents($file), $matches);

            foreach($matches[1] as $import)
            {
                $this->assertTrue(
                    self::isAllowedImport($import),
                    sprintf('%s imports [%s], which the Metadata module must not depend on.', basename($file), $import)
                );
            }
        }
    }

    private static function isAllowedImport(string $import) : bool
    {
        if(in_array($import, self::ALLOWED_CLASSES, true)) {
            return true;
        }

        foreach(self::ALLOWED_PREFIXES as $prefix) {
            if(str_starts_with($import, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function testVerifierReferencesNoIndexTypesAndWritesNothing() : void
    {
        $forbidden = array(
            'ImageIndex', 'ImageIndexEntry', 'ImageAnalyzer', 'ImageIndexer',
            'putContents', 'putData', 'file_put_contents', 'unlink', 'copyTo', 'delete', 'fopen'
        );

        $tokens = token_get_all((string)file_get_contents(self::MODULE_FOLDER.'/MetadataVerifier.php'));

        foreach($tokens as $token)
        {
            if(is_array($token) && $token[0] === T_STRING) {
                $this->assertNotContains($token[1], $forbidden, 'MetadataVerifier must stay read-only and independent of the index.');
            }
        }
    }

    public function testVerifyMetadataReturnsBoolBecauseComposerIgnoresIntegers() : void
    {
        $type = new ReflectionMethod(Builder::class, 'verifyMetadata')->getReturnType();

        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertSame('bool', $type->getName());
    }
}
