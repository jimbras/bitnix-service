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
    ReflectionException,
    ReflectionMethod,
    ReflectionParameter,
    Bitnix\Service\ConfigurationError;

/**
 * @version 0.1.0
 */
abstract class Definition implements Compilable {

    private const MAX_DEPTH     = 32;
    private const VALID_ALIAS   = '~^[a-zA-Z_\x80-\xff][a-zA-Z0-9\x80-\xff\\\\:_\-\.]*$~';
    private const TYPE_CHECKERS = [
        'bool'   => 'is_bool',
        'int'    => 'is_int',
        'float'  => 'is_float',
        'string' => 'is_string',
        'array'  => 'is_array'
    ];

    /**
     * @return array
     */
    public abstract function aliases() : array;

    /**
     * @return array
     */
    public abstract function defaults() : array;

    /**
     * @param ReflectionClass $class
     * @param array $aliases
     * @return array
     * @throws ConfigurationError
     */
    protected function bindings(ReflectionClass $class, array $aliases) : array {
        $fqcn = $class->name;

        $valid = \array_merge(
            \class_implements($fqcn),
            \class_parents($fqcn)
        );

        $resolved = [];

        foreach ($aliases as $alias) {
            if ($alias === $fqcn) {
                continue;
            } else if (isset($valid[$alias])) {
                $resolved[$alias] = true;
            } else if (!\interface_exists($alias)
                && !\class_exists($alias)
                && \preg_match(self::VALID_ALIAS, $alias)) {

                $resolved[$alias] = true;
            } else {
                throw new ConfigurationError(\sprintf(
                    'Cannot use "%s" as an alias for %s',
                        $alias,
                        $fqcn
                ));
            }

        }

        return \array_keys($resolved);
    }

    /**
     * @param string $fqcn
     * @param bool $concrete
     * @return ReflectionClass
     * @throws ConfigurationError
     */
    protected function class(string $fqcn, bool $concrete = true) : ReflectionClass {

        try {
            $class = new ReflectionClass($fqcn);
        } catch (ReflectionException $x) {
            throw new ConfigurationError(\sprintf(
                'Unable to find class %s', $fqcn
            ));
        }

        if ($concrete && !$class->isInstantiable()) {
            throw new ConfigurationError(\sprintf(
                'Uninstantiable class %s', $fqcn
            ));
        }

        return $class;
    }

    /**
     * @param ReflectionClass $class
     * @param array $arguments
     * @return array
     * @throws ConfigurationError
     */
    protected function constructor(ReflectionClass $class, array $arguments) : array {
        $constructor = $class->getConstructor();
        if (!$constructor || 0 === $constructor->getNumberOfParameters()) {
            return [];
        }
        return $this->arguments($constructor, $arguments);
    }

    /**
     * @param ReflectionClass $class
     * @param string $name
     * @return ReflectionMethod
     * @throws ConfigurationError
     */
    protected function method(ReflectionClass $class, string $name) : ReflectionMethod {
        try {
            $method = $class->getMethod($name);
        } catch (ReflectionException $x) {
            throw new ConfigurationError(\sprintf(
                'Class %s has no method %s', $class->name, $name
            ));
        }

        return $method;
    }

    /**
     * @param ReflectionClass $class
     * @param array $methods
     * @return array
     * @throws ConfigurationError
     */
    protected function methods(ReflectionClass $class, array $methods) : array {
        $resolved = [];

        foreach ($methods as $name => $calls) {

            $method = $this->method($class, $name);

            $resolved[$name] = [
                'static' => $method->isStatic()
            ];

            $args = [];

            foreach ($calls as $input) {
                $args[] = $this->arguments($method, $input);
            }

            $resolved[$name]['arguments'] = $args;

        }

        return $resolved;
    }

    /**
     * @param ReflectionMethod $method
     * @param array $arguments
     * @return array
     * @throws ConfigurationError
     */
    protected function arguments(ReflectionMethod $method, array $arguments) : array {
        $args = [];

        if (!$method->isPublic()) {
            throw new ConfigurationError(\sprintf(
                'Method %s is not public', $this->methodName($method)
            ));
        }

        $parameters = $method->getParameters();

        foreach ($parameters as $parameter) {

            if (null !== ($expr = $this->classArgument($parameter, $arguments))) {
                $args[] = $expr;
            } else if (null !== ($exprs = $this->userArguments($parameter, $arguments))) {
                $args = [...$args, ...$exprs];
            } else if (null !== ($expr = $this->defaultArgument($parameter))) {
                $args[] = $expr;
            } else if (!$parameter->isVariadic()) {
                throw new ConfigurationError(\sprintf(
                    'Unable to resolve parameter $%s while resolving %s',
                        $parameter->name,
                        $this->methodName($method)
                ));
            }
        }

        return $args;
    }

    /**
     * @param ReflectionParameter $parameter
     * @param array $arguments
     * @return null|Expression
     * @throws ConfigurationError
     */
    private function classArgument(ReflectionParameter $parameter, array &$arguments) : ?Expression {
        try {
            $class = $parameter->getClass();
        } catch (ReflectionException $x) {
            throw new ConfigurationError($x->getMessage());
        }

        if (!$class) {
            return null;
        }

        if ($parameter->isVariadic() && $arguments) {
            $key = \array_key_last($arguments);
            $tag = $arguments[$key];
            unset($arguments[$key]);

            if (!\is_string($tag)) {
                throw new ConfigurationError(\sprintf(
                    'Variadic parameter $%s from method %s expected string tag value, got %s',
                        $parameter->name,
                        $this->methodName($parameter->getDeclaringFunction()),
                        \gettype($tag)
                ));
            }

            return new Expression(\sprintf('$this->tagged(\'%s\')', $tag));
        }

        return new Expression(\sprintf(
            '$this->fetch(\'%s\', %s)',
                $class->name,
                $parameter->isDefaultValueAvailable() || $parameter->allowsNull()
                    ? 'false'
                    : 'true'
        ));
    }

    /**
     * @param ReflectionParameter $parameter
     * @param array $arguments
     * @return null|Expression
     * @throws ConfigurationError
     */
    private function userArguments(ReflectionParameter $parameter, array &$arguments) : ?array {
        if (!$arguments) {
            return null;
        }

        $type = (string) $parameter->getType();
        $name = $parameter->name;

        if ($parameter->isVariadic()) {
            $buffer = [];
            foreach ($arguments as $value) {
                $this->checkType($parameter, $value, $type);
                $buffer[] = $this->argumentExpression($parameter, $value);
            }
            $arguments = [];
            return $buffer;
        } else if (\array_key_exists($name, $arguments)) {
            $value = $arguments[$name];
            unset($arguments[$name]);
            $this->checkType($parameter, $value, $type);
            return [$this->argumentExpression($parameter, $value)];
        } else if (\is_int(\array_key_first($arguments))) {
            $value = \array_shift($arguments);
            $this->checkType($parameter, $value, $type);
            return [$this->argumentExpression($parameter, $value)];
        } else {
            return null;
        }
    }

    /**
     * @param ReflectionParameter $parameter
     * @return null|Expression
     */
    private function defaultArgument(ReflectionParameter $parameter) : ?Expression {

        if ($parameter->isDefaultValueAvailable()) {
            return $this->argumentExpression($parameter, $parameter->getDefaultValue());
        }

        if ($parameter->allowsNull()) {
            return $this->argumentExpression($parameter, null);
        }

        return null;
    }

    /**
     * @param ReflectionParameter $parameter
     * @param mixed $value
     * @param string $type
     * @throws ConfigurationError
     */
    private function checkType(ReflectionParameter $parameter, $value, string $type) : void {

        if (!$type
            || (isset(self::TYPE_CHECKERS[$type]) && \call_user_func(self::TYPE_CHECKERS[$type], $value))
            || (null === $value && $parameter->allowsNull())) {
            return;
        }

        if ($parameter->isVariadic() && \is_array($value)) {
            $t = (string) $parameter->getType();
            foreach ($value as $v) {
                $this->checkType($parameter, $v, $t);
            }
            return;
        }

        throw new ConfigurationError(\sprintf(
            'Unsupported %s parameter $%s for method %s%s',
                \gettype($value),
                $parameter->name,
                $this->methodName($parameter->getDeclaringFunction()),
                $type ? (', ' . $type . ' required...') : ''
        ));
    }

    /**
     * @param ReflectionParameter $parameter
     * @param mixed $value
     * @param int $level
     * @return Expression
     * @throws ConfigurationError
     */
    private function argumentExpression(ReflectionParameter $parameter, $value, int $level = 0) : Expression {
        if (null === $value) {
            return new Expression('null');
        } else if (\is_bool($value)) {
            return new Expression(\sprintf('%s', $value ? 'true' : 'false'));
        } else if (\is_int($value) || \is_float($value)) {
            return new Expression(\sprintf('%s', $value));
        } else if (\is_string($value)) {
            return new Expression(\var_export($value, true));
        } else if (\is_array($value)) {
            $prefix = $parameter->isVariadic() ? '...' : '';

            if (empty($value)) {
                return new Expression($prefix . '[]');
            }

            if (++$level === self::MAX_DEPTH) {
                throw new ConfigurationError(\sprintf(
                    'Too many nesting levels for parameter $%s from method %s',
                        $parameter->name,
                        $this->methodName($parameter->getDeclaringFunction())
                ));
            }

            $buffer = [];
            foreach ($value as $k => $v) {
                if (\is_string($k)) {
                    $buffer[] = \sprintf(
                        '%s => %s',
                            $this->argumentExpression($parameter, $k, $level),
                            $this->argumentExpression($parameter, $v, $level)
                    );
                } else {
                    $buffer[] = (string) $this->argumentExpression($parameter, $v, $level);
                }
            }

            return new Expression(\sprintf(
                '%s[%s]',
                    $prefix,
                    \implode(', ', $buffer)
            ));

        } else {
            throw new ConfigurationError(\sprintf(
                'Unsupported %s type for parameter $%s from method %s',
                    \gettype($value),
                    $parameter->name,
                    $this->methodName($parameter->getDeclaringFunction())
            ));
        }
    }

    /**
     * @param ReflectionMethod $method
     * @return string
     */
    private function methodName(ReflectionMethod $method) : string {
        return $method->class . '::' . $method->name;
    }

    /**
     * @return string
     */
    public function __toString() : string {
        return static::CLASS;
    }
}
