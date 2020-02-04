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
    Bitnix\Service\ConfigurationError;

/**
 * @version 0.1.0
 */
final class ConcreteService extends Definition implements Provider {

    /**
     * @var string
     */
    private string $fqcn;

    /**
     * @var array
     */
    private array $aliases;

    /**
     * @var array
     */
    private array $defaults;

    /**
     * @var bool
     */
    private bool $prototype;

    /**
     * @var array
     */
    private array $tags;

    /**
     * @var array
     */
    private array $expressions;

    /**
     * @param string $fqcn
     * @param null|string $impl
     * @param bool $prototype
     * @param array $constructor
     * @param array $methods
     * @param array $aliases
     * @param array $tags
     * @param array $defaults
     * @throws ConfigurationError
     */
    public function __construct(
        string $fqcn,
        string $impl = null,
        bool $prototype = false,
        array $constructor = [],
        array $methods = [],
        array $aliases = [],
        array $tags = [],
        array $defaults = []
    ) {

        if ($impl) {
            $class = $this->class($impl);
            $this->implements($class, $fqcn);
            $this->fqcn = $fqcn;
        } else {
            $class = $this->class($fqcn);
            $this->fqcn = $class->name;
        }

        $this->expressions = $this->expressions(
            $class->name,
            $this->constructor($class, $constructor),
            $this->methods($class, $methods)
        );

        $this->aliases = $this->bindings($class, $aliases);

        $this->prototype = $prototype;
        $this->tags = $tags;
        $this->defaults = $defaults;
    }

    /**
     * @param ReflectionClass $class
     * @param string $fqcn
     * @throws ConfigurationError
     */
    private function implements(ReflectionClass $class, string $fqcn) : void {
        $impl = $class->name;
        if (isset(\class_implements($impl)[$fqcn])
            || isset(\class_parents($impl)[$fqcn])) {
            return;
        }

        throw new ConfigurationError(\sprintf(
            '%s cannot be bound to %s', $impl, $fqcn
        ));
    }

    /**
     * @param string $fqcn
     * @param array $constructor
     * @param array $methods
     * @return array
     */
    private function expressions(string $fqcn, array $constructor, array $methods) : array {
        if (!$methods) {
            return [new Expression(\sprintf(
                'return new \%s(%s);',
                    $fqcn,
                    \implode(', ', $constructor)
            ))];
        }

        $buffer = [new Expression(\sprintf(
            '$service = new \%s(%s);',
                $fqcn,
                \implode(', ', $constructor)
        ))];

        foreach ($methods as $name => $info) {
            $prefix = $info['static'] ? \sprintf('\%s::', $fqcn) : '$service->';
            foreach ($info['arguments'] as $arguments) {
                $buffer[] = new Expression(\sprintf(
                    '%s%s(%s);',
                        $prefix,
                        $name,
                        \implode(', ', $arguments)
                ));
            }
        }

        $buffer[] = new Expression('return $service;');

        return $buffer;
    }

    /**
     * @return string
     */
    public function binding() : string {
        return $this->fqcn;
    }

    /**
     * @return array
     */
    public function aliases() : array {
        return $this->aliases;
    }

    /**
     * @return array
     */
    public function defaults() : array {
        return $this->defaults;
    }

    /**
     * @return bool
     */
    public function prototype() : bool {
        return $this->prototype;
    }

    /**
     * @return array
     */
    public function tags() : array {
        return $this->tags;
    }

    /**
     * @param string $name
     * @return Method
     */
    public function handler(string $name) : Method {
        return new Method(
            $name,
            false,
            Method::PRIVATE,
            null,
            '\\' . $this->fqcn,
            ...$this->expressions
        );
    }

    /**
     * @param Compiler $compiler
     */
    public function compile(Compiler $compiler) : void {
        $compiler->provider($this);
    }
}
