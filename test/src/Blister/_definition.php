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

use ReflectionClass,
    ReflectionMethod,
    RuntimeException;

// class
class Concrete {}
interface Uninstantiable {}

// constructor
class EmptyConstructor {
    public function __construct() {}
}

class AllSupportedArguments {
    public function __construct(
        Uninstantiable $required,
        Concrete       $optional = null,
        bool           $bool    = false,
        int            $int     = 1,
        float          $float   = 2.3,
        ?string        $string,
        array          $empty   = [],
        array          $array   = [1, 2, 'three' => 'four'],
                       $untyped = 'can be anything',
        string         ...$variadic
    ) {}
}

class VariadicServices {
    public function __construct(Uninstantiable ...$whatever) {}
}

class UnresolvedParameters {
    public function __construct(int $value) {}
}

class UnsupportedArguments {
    public function __construct($o) {}
}

class TooManyNestingLevels {
    // 33 levels (32 limit)
    public const BAD = [
        [[[[[[[[[[[[[[[[[[[[[[[[[[[[[[[[]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]
    ];
    public function __construct(array $data = self::BAD) {}
}

class PrivateConstructor {
    private function __construct(int $value) {}
}

class UndefinedClassParameter {
    public function __construct(X $x) {}
}

// methods
class MethodCalls {
    public function foo(string $bar = 'bar', string $zig = 'zag') {}
    private function bar() {}
}

// aliases
interface Foo {}
class Bar implements Foo {}

class MockedDefinition extends Definition {

    public function testClass(string $class, bool $required = true) : ReflectionClass {
        return $this->class($class, $required);
    }

    public function testConstructor(ReflectionClass $class, array $arguments = []) : array {
        return $this->constructor($class, $arguments);
    }

    public function testMethods(ReflectionClass $class, array $methods = []) : array {
        return $this->methods($class, $methods);
    }

    public function testBindings(\ReflectionClass $class, array $aliases = []) : array {
        return $this->bindings($class, $aliases);
    }

    public function compile(Compiler $c) : void {
        throw new RuntimeException('Not implemented');
    }

    public function binding() : string {
        throw new RuntimeException('Not implemented');
    }

    public function aliases() : array {
        throw new RuntimeException('Not implemented');
    }

    public function defaults() : array {
        throw new RuntimeException('Not implemented');
    }
}
