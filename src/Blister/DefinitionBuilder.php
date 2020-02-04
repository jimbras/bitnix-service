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

use Bitnix\Service\Binder,
    Bitnix\Service\ServiceBuilder;

/**
 * @version 0.1.0
 */
final class DefinitionBuilder implements ServiceBuilder {

    private const MAKE_CONCRETE = 'makeConcrete';
    private const MAKE_FACTORY  = 'makeFactory';
    private const MAKE_WRAPPER  = 'makeWrapper';

    /**
     * @var Collector
     */
    private Collector $collector;

    /**
     * @var string
     */
    private string $binding;

    /**
     * @var array
     */
    private array $constructor = [];

    /**
     * @var array
     */
    private array $methods = [];

    /**
     * @var array
     */
    private array $aliases = [];

    /**
     * @var array
     */
    private array $tags = [];

    /**
     * @var array
     */
    private array $defaults = [];

    /**
     * @var bool
     */
    private bool $prototype = false;

    /**
     * @var string
     */
    private string $handler = self::MAKE_CONCRETE;

    /**
     * @var array
     */
    private array $arguments = ['impl' => null];

    /**
     * @param Collector $collector
     * @param string $binding
     */
    public function __construct(Collector $collector, string $binding) {
        $this->collector = $collector;
        $this->binding = $binding;
    }

    /**
     * @param string $impl
     * @return self
     * @throws ConfigurationError
     */
    public function to(string $impl) : ServiceBuilder {
        $this->handler = self::MAKE_CONCRETE;
        $this->arguments = [
            'impl' => $impl
        ];
        return $this;
    }

    /**
     * @param string $fqcn
     * @param string $method
     * @return self
     * @throws ConfigurationError
     */
    public function toFactory(string $fqcn, string $method) : ServiceBuilder {
        $this->handler = self::MAKE_FACTORY;
        $this->arguments = [
            'factory' => $fqcn,
            'method'  => $method
        ];
        return $this;
    }

    /**
     * @param string $fqcn
     * @param string $method
     * @param int $priority
     * @return self
     * @throws ConfigurationError
     */
    public function toWrapper(string $fqcn, string $method, int $priority = 0) : ServiceBuilder {
        $this->handler = self::MAKE_WRAPPER;
        $this->arguments = [
            'wrapper'  => $fqcn,
            'method'   => $method,
            'priority' => $priority
        ];
        return $this;
    }

    /**
     * @param array $args
     * @return self
     * @throws ConfigurationError
     */
    public function withConstructor(array $args) : ServiceBuilder {
        $this->constructor = $args;
        return $this;
    }

    /**
     * @param string $method
     * @param array $args
     * @return self
     * @throws ConfigurationError
     */
    public function withMethod(string $method, array $args = []) : ServiceBuilder {
        $this->methods[$method] ??= [];
        $this->methods[$method][] = $args;
        return $this;
    }

    /**
     * @param string $alias
     * @return self
     * @throws ConfigurationError
     */
    public function withAlias(string $alias) : ServiceBuilder {
        $this->aliases[$alias] = true;
        return $this;
    }

    /**
     * @param string $tag
     * @param int $priority
     * @return self
     * @throws ConfigurationError
     */
    public function withTag(string $tag, int $priority = 0) : ServiceBuilder {
        $this->tags[$tag] = new Tag($tag, $priority);
        return $this;
    }

    /**
     * @return self
     * @throws ConfigurationError
     */
    public function asPrototype() : ServiceBuilder {
        $this->prototype = true;
        return $this;
    }

    /**
     * @param string $tag
     * @param callable $provider
     * @return self
     * @throws ConfigurationError
     */
    public function withDefault(string $fqcn, callable $provider) : ServiceBuilder {
        $this->defaults[$fqcn] = $provider;
        return $this;
    }

    /**
     * @return Binder
     * @throws ConfigurationError
     */
    public function done() : Binder {
        return $this->collector
            ->collect($this->{$this->handler}());
    }

    /**
     * @return Definition
     * @throws ConfigurationError
     */
    private function makeConcrete() : Definition {
        return new ConcreteService(
            $this->binding,
            $this->arguments['impl'],
            $this->prototype,
            $this->constructor,
            $this->methods,
            \array_keys($this->aliases),
            \array_values($this->tags),
            $this->defaults
        );
    }

    /**
     * @return Definition
     * @throws ConfigurationError
     */
    private function makeFactory() : Definition {
        $factory = $this->arguments['factory'];
        $method = $this->arguments['method'];

        $arguments = [];
        if (isset($this->methods[$method])) {
            $arguments = \array_pop($this->methods[$method]);
            unset($this->methods[$method]);
        }

        return new ServiceFactory(
            $this->binding,
            $factory,
            $method,
            $arguments,
            $this->prototype,
            $this->constructor,
            $this->methods,
            \array_keys($this->aliases),
            \array_values($this->tags),
            $this->defaults
        );
    }

    /**
     * @return Definition
     * @throws ConfigurationError
     */
    private function makeWrapper() : Definition {
        $wrapper = $this->arguments['wrapper'];
        $method = $this->arguments['method'];
        $priority = $this->arguments['priority'];

        $arguments = [];
        if (isset($this->methods[$method])) {
            $arguments = \array_pop($this->methods[$method]);
            unset($this->methods[$method]);
        }

        return new ServiceWrapper(
            $this->binding,
            $wrapper,
            $method,
            $priority,
            $this->constructor,
            $this->methods,
            $this->defaults
        );
    }

    /**
     * @return string
     */
    public function __toString() : string {
        return self::CLASS;
    }
}
