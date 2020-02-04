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
    Bitnix\Service\ConfigurationError;

/**
 * @version 0.1.0
 */
final class ServiceFactory extends Definition implements Provider {

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
     * @param string $factory
     * @param string $method
     * @param array $arguments
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
        string $factory,
        string $method,
        array $arguments = [],
        bool $prototype = false,
        array $constructor = [],
        array $methods = [],
        array $aliases = [],
        array $tags = [],
        array $defaults = []
    ) {

        $class = $this->class($fqcn, false);
        $this->fqcn = $class->name;

        $this->expressions = $this->expressions(
            $factory = $this->class($factory, false),
            $this->method($factory, $method),
            $arguments,
            $constructor,
            $methods
        );

        $this->prototype = $prototype;
        $this->aliases = $this->bindings($class, $aliases);
        $this->tags = $tags;
        $this->defaults = $defaults;
    }

    /**
     * @return array
     * @throws ConfigurationError
     */
    private function expressions(
        ReflectionClass $factory,
        ReflectionMethod $method,
        array $arguments = [],
        array $constructor = [],
        array $methods = []
    ) : array {

        $mname = $method->name;
        $fname = $factory->name;

        if (isset($methods[$mname])) {
            unset($methods[$mname]);
        }

        // TODO: check return type ?

        $buffer = [];
        $calls = [];
        $static = $method->isStatic();
        $const = $factory->isInstantiable();
        $sprefix = \sprintf('\%s::', $fname);
        $oprefix = '$factory->';

        if (!empty($methods)) {
            $methods = $this->methods($factory, $methods);
            foreach ($methods as $name => $info) {
                if ($info['static']) {
                    $prefix = $sprefix;
                } else {
                    $const = true;
                    $prefix = $oprefix;
                }

                foreach ($info['arguments'] as $args) {
                    $calls[] = \sprintf(
                        '%s%s(%s);',
                            $prefix,
                            $name,
                            \implode(', ', $args)
                    );
                }
            }
        }

        $const = !$static || ($calls && $const);
        if ($const) {
            $constructor = $this->constructor($factory, $constructor);

            $buffer[] = new Expression('static $factory = null;');
            $buffer[] = new Expression('if (null === $factory) {');
            $buffer[] = new Expression(sprintf(
                '    $factory = new \%s(%s);',
                    $fname,
                    \implode(', ', $constructor)
            ));
        }

        if ($calls) {
            $indent = $const ? '    ' : '';
            foreach ($calls as $call) {
                $buffer[] = new Expression(\sprintf(
                    '%s%s', $indent, $call
                ));
            }
        }

        if ($const) {
            $buffer[] = new Expression('}');
        }

        $buffer[] = new Expression(\sprintf(
            'return %s%s(%s);',
                $method->isStatic() ? $sprefix : $oprefix,
                $mname,
                \implode(', ', $this->arguments($method, $arguments))
        ));

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
