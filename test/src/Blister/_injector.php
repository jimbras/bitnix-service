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

use RuntimeException,
    Bitnix\Service\Container;

// autowire
class A {}
class B {
    public A $a;
    public function __construct(A $a) {
        $this->a = $a;
    }
}
class C {
    public B $b;
    public function __construct(B $b) {
        $this->b = $b;
    }
}

// prototype
class D {}

// tags
interface E {}
class E1 implements E {}
class E2 implements E {}
class E3 implements E {}

// no impl
interface F {}

// dependency cycle
class G {
    public function __construct(H $h) {}
}

class H {
    public function __construct(I $i) {}
}

class I {
    public function __construct(G $g) {}
}

// service exception
class J {
    public function __construct() {
        throw new RuntimeException('Oops!');
    }
}

// call arguments
function foo(A $a) {
    return $a;
}

// context
class K {
    public string $context;
    public function __construct(Container $c) {
        $this->context = $c->context();
    }
}

class L {
    public K $k;
    public function __construct(K $k) {
        $this->k = $k;
    }
}

class M {
    public function __construct(X $x = null) {}
}

interface Whatever {}
class MockedInjector extends Injector implements Whatever {

    public function __construct() {
        parent::__construct(
            // aliases
            ['service.alias' => A::CLASS],

            // prototypes
            [D::CLASS => true],

            // tags
            ['service.tag' => [E1::CLASS, E2::CLASS, E3::CLASS], 'error.tag' => [I::CLASS]],
        );
    }

    protected function service(string $fqcn) : ?object {
        // simulate factory
        if (L::CLASS === $fqcn) {
            return new L(new K($this));
        }
        return null;
    }

    protected function wrap(string $fqcn, object $object) : object {
        return $object;
    }

}
