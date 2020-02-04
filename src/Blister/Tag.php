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

use Bitnix\Service\ConfigurationError;

/**
 * @version 0.1.0
 */
final class Tag {

    private const VALID = '~^[a-zA-Z_\x80-\xff][a-zA-Z0-9\x80-\xff\\\\:_\-\.]*$~';

    /**
     * @var string
     */
    private string $name;

    /**
     * @var int
     */
    private int $priority;

    /**
     * @param string $name
     * @param int $priority
     * @throws ConfigurationError
     */
    public function __construct(string $name, int $priority = 0) {
        if (!\preg_match(self::VALID, $name)) {
            throw new ConfigurationError(\sprintf(
                'Invalid tag name: "%s"', $name
            ));
        }
        $this->name = $name;
        $this->priority = $priority;
    }

    /**
     * @return string
     */
    public function name() : string {
        return $this->name;
    }

    /**
     * @return int
     */
    public function priority() : int {
        return $this->priority;
    }

    /**
     * @return string
     */
    public function __toString() : string {
        return \sprintf(
            '%s (%s)',
                $this->name,
                $this->priority
        );
    }
}
