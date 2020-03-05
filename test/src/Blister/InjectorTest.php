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
    Bitnix\Service\Container,
    Bitnix\Service\ServiceFailure,
    Bitnix\Service\UnknownService,
    PHPUnit\Framework\TestCase,
    Psr\Container\ContainerInterface,
    Psr\Container\ContainerExceptionInterface,
    Psr\Container\NotFoundExceptionInterface;

require_once __DIR__ . '/_injector.php';

/**
 * @version 0.1.0
 */
class InjectorTest extends TestCase {

    private Injector $container;

    public function setUp() : void {
        $this->container = new MockedInjector();
    }

    public function testContainerResolvesItSelf() {
        foreach ([
            Container::CLASS,
            ContainerInterface::CLASS,
            Injector::CLASS,
            Whatever::CLASS
        ] as $fqcn) {
            $this->assertSame(
                $this->container,
                $this->container->fetch($fqcn)
            );
        }
    }

    public function testContainerAutowiresConcreteServices() {
        $c = $this->container->fetch(C::CLASS);
        $this->assertInstanceOf(C::CLASS, $c);
        $this->assertInstanceOf(B::CLASS, $c->b);
        $this->assertInstanceOf(A::CLASS, $c->b->a);
    }

    public function testContainerSingletonsAllwaysReturnTheSameInstance() {
        $a = $this->container->fetch(A::CLASS);
        $this->assertSame($a, $this->container->fetch(A::CLASS));
    }

    public function testContainerPrototypesAllwaysReturnDifferentInstances() {
        $d1 = $this->container->fetch(D::CLASS);
        $d2 = $this->container->fetch(D::CLASS);
        $this->assertNotSame($d1, $d2);
    }

    public function testContainerResolvesAliases() {
        $a = $this->container->fetch('service.alias');
        $this->assertInstanceOf(A::CLASS, $a);
    }

    public function testContainerResolvesTags() {
        $tagged = $this->container->tagged('missing.tag');
        $this->assertEquals(0, \count($tagged));

        $tagged = $this->container->tagged('service.tag');
        $this->assertEquals(3, \count($tagged));
        \array_walk($tagged, fn($el) => $this->assertInstanceOf(E::CLASS, $el));
    }

    public function testPsrContainerHas() {
        $this->assertFalse($this->container->has('foo'));
        $this->assertFalse($this->container->has('missing.tag'));
        $this->assertFalse($this->container->has(J::CLASS)); // service error

        $this->assertTrue($this->container->has(A::CLASS));
        $this->assertTrue($this->container->has('service.tag'));
    }

    public function testPsrGet() {
        $a = $this->container->fetch(A::CLASS);
        $this->assertSame($a, $this->container->get(A::CLASS));

        $tagged = $this->container->get('service.tag');
        $this->assertEquals(3, \count($tagged));
    }

    public function testPsrGetServiceFailure() {
        $this->expectException(ContainerExceptionInterface::CLASS);
        $this->container->get('error.tag');
    }

    public function testPsrGetUnknownService() {
        $this->expectException(NotFoundExceptionInterface::CLASS);
        $this->container->get(X::CLASS);
    }

    public function testMissingRequiredServicesThrowsUnknownService() {
        $this->expectException(UnknownService::CLASS);
        $this->container->fetch(X::CLASS);
    }

    public function testMissingOptionalServicesReturnNull() {
        $this->assertNull($this->container->fetch(X::CLASS, false));
    }

    public function testUnimplementedRequiredServicesThrowsServiceFailure() {
        $this->expectException(ServiceFailure::CLASS);
        $this->container->fetch(F::CLASS);
    }

    public function testUnimplementedOptionalServicesReturnNull() {
        $this->assertNull($this->container->fetch(F::CLASS, false));
    }

    public function testDependencyCyclesThrowsUnexpectedServiceCycle() {
        $this->expectException(UnexpectedServiceCycle::CLASS);
        $this->container->fetch(I::CLASS);
    }

    public function testExceptionsThrownFromTheServicePropagateToClient() {
        $this->expectException(RuntimeException::CLASS);
        $this->container->fetch(J::CLASS);
    }

    public function testCallResolvesClosureArguments() {

        $this->assertInstanceOf(
            A::CLASS,
            $this->container->call(fn(A $a) => $a)
        );

    }

    public function foo(A $a) {
        return $a;
    }

    public function testCallResolvesMethodArguments() {
        $this->assertInstanceOf(
            A::CLASS,
            $this->container->call([$this, 'foo'])
        );
    }

    public function testCallResolvesFunctionArguments() {
        $this->assertInstanceOf(
            A::CLASS,
            $this->container->call(__NAMESPACE__ . '\\foo')
        );
    }

    public function testCallResolvesVariadicParameters() {
        $result = $this->container->call(fn($first, ...$others) => [$first, ...$others], [1]);
        $this->assertEquals([1], $result);

        $result = $this->container->call(fn($first, ...$others) => [$first, ...$others], [1, 2, 3]);
        $this->assertEquals([1, 2, 3], $result);

        $result = $this->container->call(fn($first, ...$others) => [$first, ...$others], [2, 3, 'first' => 1]);
        $this->assertEquals([1, 2, 3], $result);

        $this->assertInstanceOf(A::CLASS, $this->container->call(fn(A ...$a) => $a[0]));
    }

    public function testCallResolvesParametersWithDefaultValues() {
        $this->assertEquals('bar', $this->container->call(fn(string $foo = 'bar') => $foo));
        $this->assertNUll($this->container->call(fn(?string $foo) => $foo));
    }

    public function testUnresolvedParametersThrowUnresolvedParameterException() {
        $this->expectException(UnresolvedParameter::CLASS);
        $this->container->call(fn(bool $flag) => $flag);
    }

    public function testContextReturnsNullIfContainerIsNotResolvingServices() {
        $this->assertNull($this->container->context());
    }

    public function testContextReturnsClassOfServiceBeingResolved() {
        $k = $this->container->fetch(K::CLASS);
        $this->assertEquals(K::CLASS, $k->context);

        $l = $this->container->fetch(L::CLASS);
        $this->assertEquals(L::CLASS, $l->k->context);
    }

    public function testAutowiringCatchesReflectionExceptions() {
        $this->expectException(UnknownService::CLASS);
        $this->container->fetch(M::CLASS);
    }

    public function testToString() {
        $this->assertIsString((string) $this->container);
    }

}
