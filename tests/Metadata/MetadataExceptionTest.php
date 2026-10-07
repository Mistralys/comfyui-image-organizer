<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests\Metadata;

use Mistralys\ComfyUIOrganizer\ImageIndexer;
use Mistralys\ComfyUIOrganizer\Metadata\MetadataException;
use Mistralys\ComfyUIOrganizer\OrganizerException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[Group('metadata')]
final class MetadataExceptionTest extends TestCase
{
    /**
     * @return array<string,int> Constant name, qualified by its declaring class, to value.
     */
    private static function collectCodes() : array
    {
        $codes = array();

        foreach(array(MetadataException::class, OrganizerException::class, ImageIndexer::class) as $class)
        {
            $reflection = new ReflectionClass($class);

            foreach($reflection->getReflectionConstants() as $constant)
            {
                $value = $constant->getValue();

                if($constant->getDeclaringClass()->getName() !== $class || !is_int($value)) {
                    continue;
                }

                $codes[$class.'::'.$constant->getName()] = $value;
            }
        }

        return $codes;
    }

    public function testErrorCodesAreUnique() : void
    {
        $codes = self::collectCodes();

        $this->assertNotEmpty($codes);
        $this->assertSame(
            array(),
            array_diff_key($codes, array_unique($codes)),
            'Two constants share the same code.'
        );
    }

    public function testRetiredCodeIsUnassigned() : void
    {
        $this->assertNotContains(180904, self::collectCodes());
    }

    public function testIndexerActionCodes() : void
    {
        $codes = self::collectCodes();
        $prefix = ImageIndexer::class.'::';

        $this->assertSame(180901, $codes[$prefix.'ACTION_UPDATE_PATHS'] ?? null);
        $this->assertSame(180902, $codes[$prefix.'ACTION_SKIP_NO_CHECKPOINT'] ?? null);
        $this->assertSame(180903, $codes[$prefix.'ACTION_IMAGE_INDEXED'] ?? null);
        $this->assertSame(180905, $codes[$prefix.'ACTION_SKIP_UNRECOGNIZED_SIDECAR'] ?? null);
        $this->assertSame(180906, $codes[$prefix.'ACTION_SKIP_INVALID_SIDECAR'] ?? null);
    }
}
