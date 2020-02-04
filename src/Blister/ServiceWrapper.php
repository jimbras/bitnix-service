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
final class ServiceWrapper extends Definition implements Wrapper {

    /**
     * @var string
     */
    private string $fqcn;

    /**
     * @var string
     */
    private string $wrapper;

    /**
     * @var array
     */
    private array $defaults;

    /**
     * @var int
     */
    private int $priority;

    /**
     * @var array
     */
    private array $expressions;

    /**
     * @param string $fqcn
     * @param string $wrapper
     * @param string $method
     * @param int $priority
     * @param array $constructor
     * @param array $methods
     * @param array $defaults
     * @throws ConfigurationError
     */
    public function __construct(
        string $fqcn,
        string $wrapper,
        string $method,
        int $priority = 0,
        array $constructor = [],
        array $methods = [],
        array $defaults = []
    ) {

        $class = $this->class($fqcn, false);
        $this->fqcn = $class->name;

        $this->expressions = $this->expressions(
            $wrapper = $this->class($wrapper, false),
            $this->method($wrapper, $method),
            $constructor,
            $methods
        );

        $this->wrapper = $wrapper->name;
        $this->priority = $priority;
        $this->defaults = $defaults;
    }

    /**
     * @return array
     * @throws ConfigurationError
     */
    private function expressions(
        ReflectionClass $wrapper,
        ReflectionMethod $method,
        array $constructor = [],
        array $methods = []
    ) : array {

        $mname = $method->name;
        $fname = $wrapper->name;

        if (isset($methods[$mname])) {
            unset($methods[$mname]);
        }

        // TODO: check return type ?

        $buffer = [];
        $calls = [];
        $static = $method->isStatic();
        $const = $wrapper->isInstantiable();
        $sprefix = \sprintf('\%s::', $fname);
        $oprefix = '$wrapper->';

        if (!empty($methods)) {
            $methods = $this->methods($wrapper, $methods);
            foreach ($methods as $name => $info) {
                if ($info['static']) {
                    $prefix = $sprefix;
                } else {
                    $const = true;
                    $prefix = $oprefix;
                }

                foreach ($info['arguments'] as $arguments) {
                    $calls[] = \sprintf(
                        '%s%s(%s);',
                            $prefix,
                            $name,
                            \implode(', ', $arguments)
                    );
                }
            }
        }

        $const = !$static || ($calls && $const);
        if ($const) {
            $constructor = $this->constructor($wrapper, $constructor);

            $buffer[] = new Expression('static $wrapper = null;');
            $buffer[] = new Expression('if (null === $wrapper) {');
            $buffer[] = new Expression(sprintf(
                '    $wrapper = new \%s(%s);',
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
            'return %s%s($service);',
                $method->isStatic() ? $sprefix : $oprefix,
                $mname
        ));

        return $buffer;
    }

    /**
     * @return string
     */
    public function binding() : string {
        return $this->wrapper;
    }

    /**
     * @return string
     */
    public function wrapping() : string {
        return $this->fqcn;
    }

    /**
     * @return array
     */
    public function aliases() : array {
        return [];
    }

    /**
     * @return array
     */
    public function defaults() : array {
        return $this->defaults;
    }

    /**
     * @return int
     */
    public function priority() : int {
        return $this->priority;
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
            [new Expression(\sprintf('\%s $service', $this->fqcn))],
            '\\' . $this->fqcn,
            ...$this->expressions
        );
    }

    /**
     * @param Compiler $compiler
     */
    public function compile(Compiler $compiler) : void {
        $compiler->wrapper($this);
    }

}
