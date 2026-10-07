<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer\Tests;

use Mistralys\ComfyUIOrganizer\SessionState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SessionStateTest extends TestCase
{
    protected function setUp() : void
    {
        $_SESSION = array();
    }

    /**
     * @return array<string,array{0:mixed}>
     */
    public static function wrongTypeProvider() : array
    {
        return array(
            'int' => array(5),
            'array' => array(array('a')),
            'null' => array(null),
            'float' => array(1.5),
        );
    }

    public function testAbsentKeyReturnsDefaults() : void
    {
        $this->assertSame('', SessionState::getString('key'));
        $this->assertSame('fallback', SessionState::getString('key', 'fallback'));
        $this->assertFalse(SessionState::getBool('key'));
    }

    #[DataProvider('wrongTypeProvider')]
    public function testDefaultsForWrongType(mixed $value) : void
    {
        $_SESSION['key'] = $value;

        $this->assertSame('fallback', SessionState::getString('key', 'fallback'));
        $this->assertFalse(SessionState::getBool('key'));
    }

    public function testStringRoundTrip() : void
    {
        SessionState::set('key', 'value');

        $this->assertSame('value', SessionState::getString('key', 'fallback'));
        $this->assertSame('value', $_SESSION['key']);
        $this->assertFalse(SessionState::getBool('key'));
    }

    public function testBoolRoundTrip() : void
    {
        SessionState::set('key', true);
        $this->assertTrue(SessionState::getBool('key'));
        $this->assertSame('fallback', SessionState::getString('key', 'fallback'));

        SessionState::set('key', false);
        $this->assertFalse(SessionState::getBool('key'));
    }
}
