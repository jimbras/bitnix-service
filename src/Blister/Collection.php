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

/**
 * @version 0.1.0
 */
final class Collection {

    private const INDENT        = '    ';
    private const EMPTY         = '[]';
    private const OPEN          = '[';
    private const CLOSE         = ']';
    private const COMMA         = ',';
    private const NESTED_COMMA  = ', ';
    private const NESTED_LIST   = '[%s]';
    private const NESTED_MAP    = '%s => [%s]';
    private const LIST          = '%s';
    private const MAP           = '%s => %s';

    /**
     * @var array
     */
    private array $data;

    /**
     * @param array $data
     */
    public function __construct(array $data) {
        $this->data = empty($data) ? [] : $this->lines($data);
    }

    /**
     * @param array $data
     * @return array
     */
    private function lines(array $data) : array {

        $buffer = [];
        foreach ($data as $key => $value) {
            if (\is_array($value)) {

                if (\is_int($key)) {
                    $buffer[] = new Expression(\sprintf(
                        self::NESTED_LIST,
                            \implode(self::NESTED_COMMA, $this->lines($value))
                    ));
                } else {
                    $buffer[] = new Expression(\sprintf(
                        self::NESTED_MAP,
                            \var_export($key, true),
                            \implode(self::NESTED_COMMA, $this->lines($value))
                    ));
                }


            } else if (\is_int($key)) {
                $buffer[] = new Expression(
                    \sprintf(
                        self::LIST,
                            \var_export($value, true)
                    )
                );
            } else {
                $buffer[] = new Expression(
                    \sprintf(
                        self::MAP,
                            \var_export($key, true),
                            \var_export($value, true)
                    )
                );
            }
        }
        return $buffer;
    }

    /**
     * @param int $pad
     * @return string
     */
    public function render(int $pad = 1) : string {
        $before = \str_repeat(self::INDENT, \max(0, $pad - 1));
        if (empty($this->data)) {
            return $before . self::EMPTY;
        }

        $pad = \max(0, $pad);
        $after = \str_repeat(self::INDENT, $pad);
        $main = \str_repeat(self::INDENT, $pad + 1);

        $buffer = [];
        foreach ($this->data as $line) {
            $buffer[] = $main . $line;
        }

        return $before
            . self::OPEN
            . \PHP_EOL
            . \implode(self::COMMA . \PHP_EOL, $buffer)
            . \PHP_EOL
            . $after
            . self::CLOSE;
    }

    /**
     * @return string
     */
    public function __toString() : string {
        return $this->render();
    }
}
