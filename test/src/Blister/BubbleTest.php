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

use ReflectionObject,
    Bitnix\Service\ConfigurationError,
    Bitnix\Service\Container,
    PHPUnit\Framework\TestCase;

require __DIR__ . '/_bubble.php';

/**
 * @version 0.1.0
 */
class BubbleTest extends TestCase {

    private const FILE = __DIR__ . '/_container.tpl';

    private Bubble $bubble;
    private static int $counter = 0;

    public function setUp() : void {
        $this->bubble = new Bubble();
    }

    public function tearDown() : void {
        if (\is_file(self::FILE)) {
            \unlink(self::FILE);
        }
    }

    public function testBubbleGeneratedValidContainerWithoutAnyDefinitions() {
        $cont = $this->compile();
        $this->assertInstanceOf(Container::CLASS, $cont);
        $this->assertEquals('', (new ReflectionObject($cont))->getNamespaceName());

        $cont = $this->compile('Foo\\Bar');
        $this->assertInstanceOf(Container::CLASS, $cont);
        $this->assertEquals('Foo\\Bar', (new ReflectionObject($cont))->getNamespaceName());
    }

    public function testBindBasicService() {
        $this->bubble
            ->bind(BasicDependency::CLASS)
                ->to(BasicDependencyImpl::CLASS)
                ->withConstructor(['foo'])
                ->withAlias('basic.dep')
            ->done();

        $cont = $this->compile();
        $service = $cont->fetch(Basic::CLASS);
        $this->assertInstanceOf(BasicDependencyImpl::CLASS, $service->required);
        $this->assertEquals('foo', $service->required->name);

        $dep = $cont->fetch(BasicDependency::CLASS);
        $this->assertInstanceOf(BasicDependencyImpl::CLASS, $dep);
        $this->assertSame($service->required, $dep);

        $dep = $cont->fetch('basic.dep');
        $this->assertInstanceOf(BasicDependencyImpl::CLASS, $dep);
        $this->assertSame($service->required, $dep);

        $this->bubble
            ->bind(Basic::CLASS)
                ->asPrototype()
                ->withDefault(BasicDependency::CLASS, fn($binder) =>
                    $binder
                        ->to(BasicDependencyImpl::CLASS)
                        ->withConstructor(['bar'])
                    ->done())
            ->done();
        $cont = $this->compile();
        $service = $cont->fetch(Basic::CLASS);
        $this->assertNotSame($service, $cont->fetch(Basic::CLASS));
        $this->assertInstanceOf(BasicDependencyImpl::CLASS, $service->required);
        $this->assertEquals('bar', $service->required->name);
    }

    public function testBasicWithMethods() {
        $this->bubble
            ->bind(BasicWithMethods::CLASS)
                ->withMethod('value', [666])
            ->done()
            ->bind(BasicDependency::CLASS)
                ->to(BasicDependencyImpl::CLASS)
                ->withConstructor(['foo'])
            ->done();
        $cont = $this->compile();
        $service = $cont->fetch(BasicWithMethods::CLASS);
        $this->assertEquals(666, $service->value);
    }

    public function testSkipRegistersUnboundService() {
        $this->bubble
            ->skip(BasicDependency::CLASS, function($binder) {
                $binder
                    ->to(BasicDependencyImpl::CLASS)
                    ->withConstructor(['foo'])
                    ->withAlias('basic.dep')
                ->done();
            });

        $cont = $this->compile();
        $service = $cont->fetch(Basic::CLASS);
        $this->assertInstanceOf(BasicDependencyImpl::CLASS, $service->required);
    }

    public function testSkipRequiresProviderToRegisterService() {
        $this->expectException(ConfigurationError::CLASS);
        $this->bubble
            ->skip(BasicDependency::CLASS, function($binder) {});
    }

    public function testBindInvalidImplementation() {
        $this->expectException(ConfigurationError::CLASS);
        $this->bubble
            ->skip(Basic::CLASS, function($binder) {
                $binder
                    ->to(BasicDependencyImpl::CLASS)
                    ->withConstructor(['foo'])
                    ->withAlias('basic.dep')
                ->done();
            });
    }

    public function testTaggedServices() {
        $this->bubble
            ->bind(FirstTag::CLASS)
                ->withTag('foo')
            ->done()
            ->bind(SecondTag::CLASS)
                ->withTag('foo', 5)
            ->done()
            ->bind(ThirdTag::CLASS)
                ->withTag('foo', 10)
            ->done();

        $cont = $this->compile();
        $tagged = $cont->tagged('foo');
        $this->assertEquals(3, \count($tagged));

        foreach ([
            ThirdTag::CLASS, SecondTag::CLASS, FirstTag::CLASS
        ] as $i => $fqcn) {
            $this->assertInstanceOf($fqcn, $tagged[$i]);
        }

        $this->assertSame($tagged, $cont->tagged('foo'));
    }

    public function testConcreteServiceFactory() {
        $this->bubble
            ->bind(BasicDependency::CLASS)
                ->toFactory(BasicDependencyFactory::CLASS, 'make')
                ->asPrototype()
            ->done();
        $cont = $this->compile();
        $basic = $cont->fetch(Basic::CLASS);
        $this->assertSame($basic, $cont->fetch(Basic::CLASS));

        $contextual= $cont->fetch(Contextual::CLASS);
        $this->assertSame($contextual, $cont->fetch(Contextual::CLASS));

        $this->assertEquals('basic', $basic->required->name);
        $this->assertEquals('contextual', $contextual->required->name);
    }

    public function testConcreteServiceFactoryWithMethodCalls() {
        $this->bubble
            ->bind(BasicDependency::CLASS)
                ->toFactory(BasicDependencyFactory::CLASS, 'make')
                ->withMethod('change', ['other'])
                ->asPrototype()
            ->done();
        $cont = $this->compile();
        $basic = $cont->fetch(Basic::CLASS);
        $this->assertSame($basic, $cont->fetch(Basic::CLASS));

        $contextual= $cont->fetch(Contextual::CLASS);
        $this->assertSame($contextual, $cont->fetch(Contextual::CLASS));

        $this->assertEquals('basic', $basic->required->name);
        $this->assertEquals('other', $contextual->required->name);

        $this->bubble
            ->bind(BasicDependency::CLASS)
                ->toFactory(BasicDependencyFactory::CLASS, 'remake')
                ->asPrototype()
                ->withMethod('remake', ['cool'])
            ->done();
        $cont = $this->compile();
        $basic = $cont->fetch(Basic::CLASS);
        $this->assertEquals('cool', $basic->required->name);
    }

    public function testStaticServiceFactory() {
        $this->bubble
            ->bind(BasicDependency::CLASS)
                ->toFactory(BasicDependencyStaticFactory::CLASS, 'make')
                ->asPrototype()
                ->withMethod('make', [])
            ->done();
        $cont = $this->compile();
        $basic = $cont->fetch(Basic::CLASS);
        $this->assertSame($basic, $cont->fetch(Basic::CLASS));

        $contextual= $cont->fetch(Contextual::CLASS);
        $this->assertSame($contextual, $cont->fetch(Contextual::CLASS));

        $this->assertEquals('basic', $basic->required->name);
        $this->assertEquals('contextual', $contextual->required->name);

        $this->bubble
            ->bind(BasicDependency::CLASS)
                ->toFactory(BasicDependencyStaticFactory::CLASS, 'make')
                ->asPrototype()
                ->withMethod('change', ['cool'])
            ->done();
        $cont = $this->compile();
        $contextual= $cont->fetch(Contextual::CLASS);
        $this->assertEquals('cool', $contextual->required->name);

        $this->bubble
            ->bind(BasicDependency::CLASS)
                ->toFactory(BasicDependencyStaticFactory::CLASS, 'remake')
                ->asPrototype()
                ->withMethod('remake', ['cool'])
            ->done();
        $cont = $this->compile();
        $basic = $cont->fetch(Basic::CLASS);
        $this->assertEquals('cool', $basic->required->name);
    }

    public function testServiceWrappers() {
        $this->bubble
            ->bind(BasicDependency::CLASS)
                ->to(BasicDependencyImpl::CLASS)
                ->withConstructor(['foo'])
            ->done()
            ->bind(BasicDependency::CLASS)
                ->toWrapper(BasicDependencyWrapper::CLASS, 'wrap')
            ->done()
            ->bind(BasicDependency::CLASS)
                ->toWrapper(BasicDependencyStaticWrapper::CLASS, 'wrap', 10)
            ->done();
        $cont = $this->compile();
        $basic = $cont->fetch(Basic::CLASS);
        $this->assertEquals('foo:static:instance', $basic->required->name);
    }

    public function testServiceWrappersWithMethods() {
        $this->bubble
            ->bind(BasicDependency::CLASS)
                ->to(BasicDependencyImpl::CLASS)
                ->withConstructor(['foo'])
            ->done()
            ->bind(BasicDependency::CLASS)
                ->toWrapper(BasicDependencyWrapper::CLASS, 'wrap')
                ->withMethod('value', ['basic'])
                ->withMethod('wrap') // ignored
            ->done()
            ->bind(BasicDependency::CLASS)
                ->toWrapper(BasicDependencyStaticWrapper::CLASS, 'wrap', 10)
                ->withMethod('value', ['changed'])
                ->withMethod('wrap') // ignored
            ->done();
        $cont = $this->compile();
        $basic = $cont->fetch(Basic::CLASS);
        $this->assertEquals('foo:changed:basic', $basic->required->name);
    }

    public function testToString() {
        $this->assertIsString((string) $this->bubble);
    }

    private function compile(string $namespace = null) : Container {
        ++self::$counter;
        $fqcn = \ltrim($namespace . '\\__Container' . self::$counter, '\\');
        \file_put_contents(self::FILE, $this->bubble->burst($fqcn));
        include self::FILE;
        return new $fqcn();
    }
}
