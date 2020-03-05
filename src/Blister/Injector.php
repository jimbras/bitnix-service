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

use Closure,
    ReflectionClass,
    ReflectionException,
    ReflectionFunction,
    ReflectionFunctionAbstract,
    ReflectionMethod,
    Throwable,
    Bitnix\Service\Container,
    Bitnix\Service\ServiceFailure,
    Bitnix\Service\UnknownService,
    Psr\Container\ContainerInterface,
    Psr\Container\ContainerExceptionInterface,
    Psr\Container\NotFoundExceptionInterface;

/**
 * @version 0.1.0
 */
abstract class Injector implements Container, ContainerInterface {

    /**
     * @var array
     */
    private array $aliases;

    /**
     * @var array
     */
    private array $resolved;

    /**
     * @var array
     */
    private array $tags;

    /**
     * @var array
     */
    private array $tagged;

    /**
     * @var array
     */
    private array $prototypes;

    /**
     * @var array
     */
    private array $me;

    /**
     * @var array
     */
    private array $resolving = [];

    /**
     * @var array
     */
    private array $cache = [];

    /**
     * @param array $aliases
     * @param array $prototypes
     * @param array $tags
     */
    public function __construct(
        array $aliases,
        array $prototypes,
        array $tags) {

        $this->aliases = $aliases;
        $this->prototypes = $prototypes;
        $this->tags = $tags;

        $this->me = \array_merge(
            [static::CLASS => static::CLASS],
            \class_implements(static::CLASS),
            \class_parents(static::CLASS)
        );
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has($id) {
        try {
            if (null !== $this->fetch($id, false)) {
                return true;
            }

            if (!empty($this->tagged($id))) {
                return true;
            }
        } catch (Throwable $x) {}


        return false;
    }

    /**
    * @param string $id
    * @return mixed
     */
    public function get($id) {
        try {
            $found = $this->fetch($id, false);
            if (null !== $found) {
                return $found;
            }

            $found = $this->tagged($id);
            if (!empty($found)) {
                return $found;
            }

        } catch (UnknownService $x) {
            // do nothing...
        } catch (Throwable $t) {
            throw new class($t->getMessage()) extends ServiceFailure implements ContainerExceptionInterface {};
        }

        throw new class(\sprintf(
            'Unable to find service "%s"', $id
        )) extends UnknownService implements NotFoundExceptionInterface {};
    }

    /**
     * @param string $fqcn
     * @return null|object
     * @throws ServiceFailure
     * @throws \Throwable
     */
    protected abstract function service(string $fqcn) : ?object;

    /**
     * @param string $fqcn
     * @param object $object
     * @return object
     * @throws ServiceFailure
     * @throws \Throwable
     */
    protected abstract function wrap(string $fqcn, object $object) : object;

    /**
     * @param string $fqcn
     * @return string
     */
    private function alias(string $fqcn) : string {
        return $this->aliases[$fqcn] ?? $fqcn;
    }

    /**
     * @return null|string
     */
    public function context() : ?string {
        if ($this->resolving) {
            $context = \array_keys($this->resolving);
            $last = \array_key_last($context);
            return $context[$last - 1] ?? $context[$last];
        }
        return null;
    }

    /**
     * @param string $fqcn
     * @throws ServiceFailure
     */
    private function capture(string $fqcn) : void {
        if (isset($this->resolving[$fqcn])) {
            throw new UnexpectedServiceCycle(\sprintf(
                'Service cyclic dependency detected: %s',
                \implode(' < ', \array_keys($this->resolving)) . ' < ' . $fqcn
            ));
        }
        $this->resolving[$fqcn] = true;
    }

    /**
     * @param bool $all
     */
    private function release(bool $all = false) : void {
        if ($all) {
            $this->resolving = [];
            return;
        }

        $key = \array_key_last($this->resolving);
        unset($this->resolving[$key]);
    }

    /**
     * @param string $fqcn
     * @param bool $required
     * @return null|object
     * @throws ServiceFailure
     * @throws \Throwable
     */
    public function fetch(string $fqcn, bool $required = true) : ?object {
        $alias = $this->alias($fqcn);

        if (isset($this->resolved[$alias])) {
            return $this->resolved[$alias];
        }

        if (!isset($this->me[$alias])) {

            try {

                $this->capture($alias);

                $service = $this->wrap(
                    $fqcn,
                    $this->service($alias) ?? $this->reflect($alias)
                );

                if (!isset($this->prototypes[$alias])) {
                    $this->resolved[$alias] = $service;
                }

                $this->release();

                return $service;

            } catch (ServiceFailure $x) {

                $this->release(true);

                if ($required) {
                    throw $x;
                }

                return null;

            } catch (Throwable $t) {

                $this->release(true);
                throw $t;

            }

        }

        return $this;
    }

    /**
     * @param string $tag
     * @return array
     * @throws ServiceFailure
     * @throws \Throwable
     */
    public function tagged(string $tag) : array {
        if (!isset($this->tagged[$tag])) {
            $this->tagged[$tag] = [];
            foreach ($this->tags[$tag] ?? [] as $fqcn) {
                $this->tagged[$tag][] = $this->fetch($fqcn);
            }
        }
        return $this->tagged[$tag];
    }

    /**
     * @param callable $callable
     * @param array $args
     * @return mixed
     * @throws ServiceFailure
     * @throws \Throwable
     */
    public function call(callable $callable, array $args = []) {
        if ($callable instanceof Closure) {
            $ref = new ReflectionFunction($callable);
            $key = $ref->getFilename() . '(' . $ref->getStartLine() . ')';
            if (!isset($this->cache[$key])) {
                $this->cache[$key] = $this->arguments($ref);
            }
        } else {
            // easy way to get callable name
            \is_callable($callable, true, $key);
            if (!isset($this->cache[$key])) {
                $ref = strpos($key, '::')
                    ? new ReflectionMethod($key)
                    : new ReflectionFunction($key);
                $this->cache[$key] = $this->arguments($ref);
            }
        }

        return $callable(...$this->cache[$key]($args));
    }

    /**
     * @param string $fqcn
     * @return object
     * @throws ServiceFailure
     * @throws \Throwable
     */
    private function reflect(string $fqcn) : object {
        $class = $this->class($fqcn);
        $constructor = $class->getConstructor();

        if (!$constructor || 0 === $constructor->getNumberOfParameters()) {
            return new $fqcn();
        }

        return new $fqcn(...$this->arguments($constructor)());
    }

    /**
     * @param string $fqcn
     * @return ReflectionClass
     * @throws ServiceFailure
     */
    private function class(string $fqcn) : ReflectionClass {
        try {
            $class = new ReflectionClass($fqcn);
        } catch (ReflectionException $x) {
            throw new UnknownService(\sprintf(
                'Unable to find service "%s"', $fqcn
            ));
        }

        if (!$class->isInstantiable()) {
            throw new ServiceFailure(\sprintf(
                'Unable to create service "%s"', $fqcn
            ));
        }

        return $class;
    }

    /**
     * @param ReflectionFunctionAbstract $fn
     * @return callable
     * @throws ServiceFailure
     */
    private function arguments(ReflectionFunctionAbstract $fn) : callable {

        $fqmn = $fn instanceof ReflectionMethod
            ? ($fn->class . '::' . $fn->name)
            : $fn->name;

        $info = [];

        try {
            foreach ($fn->getParameters() as $param) {
                $class = $param->getClass();

                $hasDefault = $param->isDefaultValueAvailable();
                $isNullable = $param->allowsNull();

                $info[$param->getName()] = [
                    $class ? $class->getName() : null,
                    $param->isVariadic(),
                    $required = !($hasDefault || $isNullable),
                    $default = $required
                        ? null
                        : ($hasDefault ? $param->getDefaultValue() : null)
                ];
            }
        } catch (ReflectionException $x) {
            throw new UnknownService($x->getMessage());
        }

        return function(array $runtime = []) use ($fqmn, $info) {
            $args = [];

            foreach ($info as $name => list($class, $variadic, $required, $default)) {

                if ($class) {
                    $args[] = $this->fetch($class, $required);
                    continue;
                }

                if ($runtime) {

                    if ($variadic) {
                        $args = [...$args, ...\array_values($runtime)];
                        break;
                    }

                    if (\array_key_exists($name, $runtime)) {
                        $args[] = $runtime[$name];
                        unset($runtime[$name]);
                        continue;
                    }

                    if (\is_int(\array_key_first($runtime))) {
                        $args[] = \array_shift($runtime);
                        continue;
                    }
                }

                if ($variadic) {
                    break;
                }

                if (!$required) {
                    $args[] = $default;
                    continue;
                }

                throw new UnresolvedParameter(\sprintf(
                    'Unable to resolve parameter $%s for %s',
                        $name,
                        $fqmn
                ));
            }

            return $args;
        };
    }

    /**
     * @return string
     */
    public function __toString() : string {
        return static::CLASS;
    }
}
