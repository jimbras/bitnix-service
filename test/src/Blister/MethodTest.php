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

use Bitnix\Service\ConfigurationError,
    PHPUnit\Framework\TestCase;

/**
 * @version 0.1.0
 */
class MethodTest extends TestCase {

    public function testInvalidName() {
        $this->expectException(ConfigurationError::CLASS);
        new Method('not valid');
    }

    public function testToString() {
        $method = new Method('foo');
        $this->assertEquals('    private function foo() {}', (string) $method);

        $expected = <<< 'CODE'
    protected static function foo(string $bar, int $times = 3) : Bar {
        return new Bar($bar, $times);
    }
CODE;
        $method = new Method(
            'foo',
            true,
            Method::PROTECTED,
            [new Expression('string $bar, int $times = 3')],
            'Bar',
            new Expression('return new Bar($bar, $times);')
        );

        $this->assertEquals($expected, (string) $method);
    }
}
