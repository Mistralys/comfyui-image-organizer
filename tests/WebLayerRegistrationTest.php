<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests;

use Mistralys\X4\UI\Ajax\AjaxMethodInterface;
use Mistralys\X4\UI\Page\BasePage;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The page and AJAX method registration scans the `Pages/` and `Ajax/Methods/` folders, so
 * these tests guard what may live there, without the application bootstrap.
 */
final class WebLayerRegistrationTest extends TestCase
{
    private const string SOURCE_FOLDER = __DIR__.'/../src/ComfyUIOrganizer';
    private const string NAMESPACE_PREFIX = 'Mistralys\\ComfyUIOrganizer\\';

    /**
     * @return ReflectionClass<object>[]
     */
    private static function classesInFolder(string $subFolder) : array
    {
        $classes = array();

        foreach((array)glob(self::SOURCE_FOLDER.'/'.$subFolder.'/*.php') as $file)
        {
            $name = self::NAMESPACE_PREFIX.str_replace('/', '\\', $subFolder).'\\'.basename((string)$file, '.php');

            self::assertTrue(class_exists($name), $name.' is not autoloadable; run composer dump-autoload.');

            $classes[] = new ReflectionClass($name);
        }

        self::assertNotEmpty($classes);

        return $classes;
    }

    public function testEveryClassInPagesFolderIsAPage() : void
    {
        foreach(self::classesInFolder('Pages') as $class)
        {
            $this->assertTrue($class->isSubclassOf(BasePage::class), $class->getName().' is not a BasePage.');
            $this->assertTrue($class->isInstantiable(), $class->getName().' cannot be instantiated.');
        }
    }

    public function testEveryClassInAjaxMethodsFolderIsAMethodWithItsOwnMethodName() : void
    {
        foreach(self::classesInFolder('Ajax/Methods') as $class)
        {
            $this->assertTrue($class->implementsInterface(AjaxMethodInterface::class), $class->getName().' is not an AJAX method.');
            $this->assertTrue($class->isInstantiable(), $class->getName().' cannot be instantiated.');

            $constant = $class->getReflectionConstant('METHOD_NAME');

            $this->assertNotFalse($constant, $class->getName().' has no METHOD_NAME.');
            $this->assertSame($class->getName(), $constant->getDeclaringClass()->getName(), $class->getName().' inherits its METHOD_NAME.');
        }
    }

    public function testMethodNamesAreUnique() : void
    {
        $names = array();

        foreach(self::classesInFolder('Ajax/Methods') as $class)
        {
            $value = $class->getConstant('METHOD_NAME');

            $this->assertIsString($value);
            $this->assertNotContains($value, $names, sprintf('METHOD_NAME [%s] of %s is used twice.', $value, $class->getName()));

            $names[] = $value;
        }
    }
}
