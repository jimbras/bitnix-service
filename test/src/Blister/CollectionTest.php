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

use PHPUnit\Framework\TestCase;

/**
 * @version 0.1.0
 */
class CollectionTest extends TestCase {

    public function testRenderLists() {
        $list = new Collection([1, 2, 3, [4, 5, 6]]);
        $expected = <<< 'CODE'
    private $var = [
        1,
        2,
        3,
        [4, 5, 6]
    ];
CODE;
        $this->assertEquals($expected, '    private $var = ' . $list . ';');
    }

    public function testRenderMap() {
        $map = new Collection(['foo' => 'bar', 'zig' => ['zag' => 'zoid', 'alpha' => 'omega']]);
        $expected = <<< 'CODE'
    private $var = [
        'foo' => 'bar',
        'zig' => ['zag' => 'zoid', 'alpha' => 'omega']
    ];
CODE;
        $this->assertEquals($expected, '    private $var = ' . $map . ';');
    }

    public function testRenderMixed() {
        $mixed = new Collection(['foo' => ['bar', 'baz'], [1, 2, 3]]);
        $expected = <<< 'CODE'
    private $var = [
        'foo' => ['bar', 'baz'],
        [1, 2, 3]
    ];
CODE;
        $this->assertEquals($expected, '    private $var = ' . $mixed . ';');
    }

    public function testRenderEmpty() {
        $empty = new Collection([]);
        $expected = <<< 'CODE'
    private $var = [];
CODE;
        $this->assertEquals($expected, '    private $var = ' . $empty . ';');
    }
}
