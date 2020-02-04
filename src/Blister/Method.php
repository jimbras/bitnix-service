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
final class Method {

    public const PUBLIC  = 'public';
    public const PROTECTED = 'protected';
    public const PRIVATE = 'private';

    private const VISIBILITY = [
        self::PUBLIC    => self::PUBLIC,
        self::PROTECTED => self::PROTECTED,
        self::PRIVATE   => self::PRIVATE
    ];

    private const VALID_NAME = '~^[a-zA-Z_][a-zA-Z_0-9]*$~';
    private const INDENT     = '    ';
    private const OPEN       = '%s%s function %s(%s)%s {';
    private const CLOSE      = '}';

    /**
     * @var string
     */
    private string $open;

    /**
     * @var array
     */
    private array $lines;

    /**
     * @param string $name
     * @param bool $static
     * @param null|string $visibility
     * @param array $parameters
     * @param null|string $returns
     * @param Expression ...$exprs
     * @throws ConfigurationError
     */
    public function __construct(
        string $name,
        bool $static = false,
        string $visibility = null,
        array $parameters = null,
        string $returns = null,
        Expression ...$exprs
    ) {
        if (!\preg_match(self::VALID_NAME, $name)) {
            throw new ConfigurationError(\sprintf(
                'Unsupported method name: "%s"', $name
            ));
        }

        $this->open = \sprintf(
            self::OPEN,
                self::VISIBILITY[$visibility] ?? self::PRIVATE,
                $static ? ' static' : '',
                $name,
                $parameters ? \implode(', ', $parameters) : '',
                $returns ? (' : ' . $returns) : ''
        );

        $this->lines = $exprs;
    }

    /**
     * @param int $pad
     * @return string
     */
    public function render(int $pad = 1) : string {
        $pad = \max(0, $pad);
        $main = \str_repeat(self::INDENT, $pad);

        if (empty($this->lines)) {
            return $main . $this->open . self::CLOSE;
        }

        $body = \str_repeat(self::INDENT, $pad * 2);

        $buffer = [$main . $this->open];

        foreach ($this->lines as $line) {
            $buffer[] = $body . $line;
        }

        $buffer[] = $main . self::CLOSE;

        return \implode(\PHP_EOL, $buffer);
    }

    /**
     * @return string
     */
    public function __toString() : string {
        return $this->render();
    }
}
