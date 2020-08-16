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
    Bitnix\Service\ConfigurationError,
    Bitnix\Service\ServiceBuilder;

/**
 * @version 0.1.0
 */
final class Bubble implements Collector {

    private const LIMIT = 32;

    /**
     * @var array
     */
    private array $bindings = [];

    /**
     * @var array
     */
    private array $definitions = [];

    /**
     * @param Definition $definition
     */
    public function collect(Definition $definition) : Collector {
        $fqcn = $definition->binding();

        $this->bindings[$fqcn] = true;
        foreach ($definition->aliases() as $fqcn) {
            $this->bindings[$fqcn] = true;
        }

        $this->definitions[$fqcn] = $definition;
        return $this;
    }

    /**
     * @param string $fqcn
     * @return ServiceBuilder
     * @throws ConfigurationError
     */
    public function bind(string $fqcn) : ServiceBuilder {
        return new DefinitionBuilder($this, $fqcn);
    }

    /**
     * @param string $fqcn
     * @param callable $provider
     * @throws ConfigurationError
     */
    public function skip(string $fqcn, callable $provider) : Binder {
        if (!isset($this->bindings[$fqcn])) {
            $provider($this->bind($fqcn));
            if (!isset($this->bindings[$fqcn])) {
                throw new ConfigurationError(\sprintf(
                    'Missing required binding: %s', $fqcn
                ));
            }
        }
        return $this;
    }

    /**
     * @param Compiler $compiler
     * @return Compiler
     * @throws ConfigurationError
     */
    public function compile(Compiler $compiler) : Compiler {

        try {

            $level = 0;
            $collected = [];

            do {

                if (++$level > self::LIMIT) {
                    throw new ConfigurationError('Too many nested services');
                }

                $current = \array_values($this->definitions);
                $collected = [...$collected, ...$current];
                $definitions = $current;
                $this->definitions = [];

                foreach ($definitions as $definition) {
                    foreach ($definition->defaults() as $class => $provider) {
                        $this->skip($class, $provider);
                    }
                }
            } while (!empty($this->definitions));

            foreach ($collected as $compilable) {
                $compilable->compile($compiler);
            }

        } finally {
            $this->bindings = $this->definitions = [];
        }

        return $compiler;
    }

    /**
     * @param string $class
     * @param string $template
     * @param array $data
     * @return string
     * @throws ConfigurationError
     */
    public function burst(string $class, string $template = Template::DEFAULT, array $data = []) : string {
        return $this->compile(new Template())->render($class, $template, $data);
    }

    /**
     * @return string
     */
    public function __toString() : string {
        return self::CLASS;
    }
}
