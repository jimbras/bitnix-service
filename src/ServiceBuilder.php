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

namespace Bitnix\Service;

/**
 * @version 0.1.0
 */
interface ServiceBuilder {

    /**
     * @param string $impl
     * @return self
     * @throws ConfigurationError
     */
    public function to(string $impl) : ServiceBuilder;

    /**
     * @param string $fqcn
     * @param string $method
     * @return self
     * @throws ConfigurationError
     */
    public function toFactory(string $impl, string $method) : ServiceBuilder;

    /**
     * @param string $fqcn
     * @param string $method
     * @param int $priority
     * @return self
     * @throws ConfigurationError
     */
    public function toWrapper(string $fqcn, string $method, int $priority = 0) : ServiceBuilder;

    /**
     * @param array $args
     * @return self
     * @throws ConfigurationError
     */
    public function withConstructor(array $args) : ServiceBuilder;

    /**
     * @param string $method
     * @param array $args
     * @return self
     * @throws ConfigurationError
     */
    public function withMethod(string $method, array $args = []) : ServiceBuilder;

    /**
     * @param string $alias
     * @return self
     * @throws ConfigurationError
     */
    public function withAlias(string $alias) : ServiceBuilder;

    /**
     * @param string $tag
     * @param int $priority
     * @return self
     * @throws ConfigurationError
     */
    public function withTag(string $tag, int $priority = 0) : ServiceBuilder;

    /**
     * @param string $tag
     * @param callable $provider
     * @return self
     * @throws ConfigurationError
     */
    public function withDefault(string $fqcn, callable $provider) : ServiceBuilder;

    /**
     * @return self
     * @throws ConfigurationError
     */
    public function asPrototype() : ServiceBuilder;

    /**
     * @return Binder
     * @throws ConfigurationError
     */
    public function done() : Binder;

}
