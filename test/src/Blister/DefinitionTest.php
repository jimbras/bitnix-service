<?php declare(strict_types=1);

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program. If not, see <https://www.gnu.org/licenses/agpl-3.0.txt>.
 */

namespace Bitnix\Service\Blister;

use Bitnix\Service\ConfigurationError,
    PHPUnit\Framework\TestCase;

require_once __DIR__ . '/_definition.php';

/**
 * @version 0.1.0
 */
class DefinitionTest extends TestCase {

    private Definition $def;

    public function setUp() : void {
        $this->def = new MockedDefinition();
    }

    public function testClassReturnsReflectionClass() {
        $class = $this->def->testClass(Concrete::CLASS);
        $this->assertEquals(Concrete::CLASS, $class->name);

        $class = $this->def->testClass(Uninstantiable::CLASS, false);
        $this->assertEquals(Uninstantiable::CLASS, $class->name);
    }

    public function testRequiredUninstantiableClassThrowsConfigurationError() {
        $this->expectException(ConfigurationError::CLASS);
        $this->def->testClass(Uninstantiable::CLASS);
    }

    public function testUnloadableClassThrowsConfigurationError() {
        $this->expectException(ConfigurationError::CLASS);
        $this->def->testClass('I\'m\\Pretty\\Sure\\This\\Class\\Cannot\\Exist');
    }

    public function testConstructorResolvesAndReturnsConstructorArguments() {
        $class = $this->def->testClass(Concrete::CLASS);
        $this->assertEquals([], $this->def->testConstructor($class));

        $class = $this->def->testClass(EmptyConstructor::CLASS);
        $this->assertEquals([], $this->def->testConstructor($class));

        $class = $this->def->testClass(AllSupportedArguments::CLASS);
        $supported = $this->def->testConstructor($class);
        $this->assertEquals(9, \count($supported)); // all but variadic
        foreach ($supported as $expr) {
            $this->assertInstanceOf(Expression::CLASS, $expr);
        }

        $supported = $this->def->testConstructor($class, ['bool' => false, 'variadic' => ['foo', 'bar']]);
        $this->assertEquals(10, \count($supported)); // with variadic ...['foo', 'bar']

        $args = [
            // Uninstantiable resolved by container
            // Concrete resolved by container
            true,
            2,
            3.4,
            'zig',
            [1, 2, 3],
            ['foo' => 'bar'],
            'what ever',
            // variadic
            'foo',
            'bar'
        ];

        $supported = $this->def->testConstructor($class, $args);
        $this->assertEquals(11, \count($supported)); // with variadic 'foo', 'bar'
    }

    public function testConstructorWithVariadicServices() {
        $class = $this->def->testClass(VariadicServices::CLASS);
        $const = $this->def->testConstructor($class);
        $this->assertEquals(1, \count($const));

        $class = $this->def->testClass(VariadicServices::CLASS);
        $const = $this->def->testConstructor($class, ['some.tag']);
        $this->assertEquals(1, \count($const));
    }

    public function testConstructorParamatersMustBeFullyResolved() {
        $this->expectException(ConfigurationError::CLASS);
        $class = $this->def->testClass(UnresolvedParameters::CLASS);
        $this->def->testConstructor($class);
    }

    public function testConstructorParamatersMustMatchExpectedType() {
        $this->expectException(ConfigurationError::CLASS);
        $class = $this->def->testClass(UnresolvedParameters::CLASS);
        $this->def->testConstructor($class, ['value']);
    }

    public function testConstructorWithVariadicServicesRequiresValidTag() {
        $this->expectException(ConfigurationError::CLASS);
        $class = $this->def->testClass(VariadicServices::CLASS);
        $this->def->testConstructor($class, [1]);
    }

    public function testConstructorParametersHaveANestingLimit() {
        $this->expectException(ConfigurationError::CLASS);
        $class = $this->def->testClass(TooManyNestingLevels::CLASS);
        $this->def->testConstructor($class);
    }

    /**
     * @param mixed $value
     * @dataProvider unsupportedParameters
     */
    public function testConstructorUnsupportedParameterTypeThrowsConfigurationError($value) {
        $this->expectException(ConfigurationError::CLASS);
        $class = $this->def->testClass(UnsupportedArguments::CLASS);
        $this->def->testConstructor($class, [$value]);
    }

    public function unsupportedParameters() : iterable {
        foreach ([
            fn() => null, // callable
            \STDIN,       // resource
            $this,        // object
            [$this]       // nested
        ] as $value) {
            yield [$value];
        }
    }

    public function testConstructorWithArgumentsMustBePublic() {
        $this->expectException(ConfigurationError::CLASS);
        $class = $this->def->testClass(PrivateConstructor::CLASS, false);
        $this->def->testConstructor($class);
    }

    public function testInvalidParametersThrowsServiceFailure() {
        $this->expectException(ConfigurationError::CLASS);
        $class = $this->def->testClass(UndefinedClassParameter::CLASS);
        $this->def->testConstructor($class);
    }

    public function testMethodArguments() {
        $class = $this->def->testClass(MethodCalls::CLASS);

        $methods = $this->def->testMethods($class, [
            'foo' => [[], ['baz'], ['zig' => 'zoid']]
        ]);

        $this->assertTrue(isset($methods['foo']));

        $foo = $methods['foo'];
        $this->assertFalse($foo['static'] ?? null);
        $this->assertIsArray($foo['arguments'] ?? null);

        $calls = $foo['arguments'];
        $this->assertEquals(3, \count($calls));
        $this->assertEquals("'bar', 'zag'", \implode(', ', $calls[0]));
        $this->assertEquals("'baz', 'zag'", \implode(', ', $calls[1]));
        $this->assertEquals("'bar', 'zoid'", \implode(', ', $calls[2]));
    }

    public function testMethodMustBePublic() {
        $this->expectException(ConfigurationError::CLASS);
        $class = $this->def->testClass(MethodCalls::CLASS);
        $this->def->testMethods($class, [
            'bar' => [[]]
        ]);
    }

    public function testMethodMustExist() {
        $this->expectException(ConfigurationError::CLASS);
        $class = $this->def->testClass(MethodCalls::CLASS);
        $this->def->testMethods($class, ['zig' => []]);
    }

    public function testAllowedAliases() {
        $class = $this->def->testClass(Bar::CLASS);

        $this->assertEquals([], $this->def->testBindings($class));
        $alias = 'my.service\\alias-really_cool-nice:name';
        $this->assertEquals(
            [Foo::CLASS, $alias],
            $this->def->testBindings($class, [Foo::CLASS, $alias, $alias, Bar::CLASS])
        );
    }

    public function testAliasesCannotReferenceServicesFromADifferentClassFamily() {
        $this->expectException(ConfigurationError::CLASS);
        $class = $this->def->testClass(Bar::CLASS);
        $this->def->testBindings($class, [get_class($this)]);
    }

    public function testAliasesCannotUnsupportedCharacters() {
        $this->expectException(ConfigurationError::CLASS);
        $class = $this->def->testClass(Bar::CLASS);
        $this->def->testBindings($class, ["\b"]);
    }

    public function testToString() {
        $this->assertIsString((string) $this->def);
    }
}
