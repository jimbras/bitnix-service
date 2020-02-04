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
class TemplateTest extends TestCase {

    private Template $template;

    public function setUp() : void {
        $this->template = new Template();
    }

    public function testClassNameMustBeValid() {
        $this->expectException(ConfigurationError::CLASS);
        $this->template->render('Invalid Class Name');
    }

    public function testTemplateFileMustExistAndBeReadable() {
        $this->expectException(ConfigurationError::CLASS);
        $this->template->render('Container', __DIR__ . '/not_a_file.tpl');
    }

    public function testExceptionsFromTemplatesAreCaught() {
        $this->expectException(ConfigurationError::CLASS);
        $this->template->render('Container', __DIR__ . '/_exception.tpl');
    }

    public function testErrorsFromTemplatesAreCaught() {
        $this->expectException(ConfigurationError::CLASS);
        $this->expectExceptionMessage('(8)');
        $this->template->render('Container', __DIR__ . '/_error.tpl');
    }

    public function testToString() {
        $this->assertIsString((string) $this->template);
    }
}
