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

use Bitnix\Service\Container;

interface BasicDependency {}
class BasicDependencyImpl implements BasicDependency {
    public string $name;
    public function __construct(string $name) {
        $this->name = $name;
    }
}

class Basic {
    public BasicDependency $required;
    public function __construct(BasicDependency $object) {
        $this->required = $object;
    }
}

class BasicWithMethods extends Basic {
    public int $value = 0;
    public function value(int $value) {
        $this->value = $value;
    }
}

class Contextual {
    public BasicDependency $required;
    public function __construct(BasicDependency $object) {
        $this->required = $object;
    }
}

class BasicDependencyFactory {
    private string $context = 'contextual';

    public function change(string $context) : void {
        $this->context = $context;
    }

    public function make(Container $container) : BasicDependency {
        if (Basic::CLASS === $container->context()) {
            return new BasicDependencyImpl('basic');
        }
        return new BasicDependencyImpl($this->context);
    }

    public function remake(string $value) : BasicDependency {
        return new BasicDependencyImpl($value);
    }
}

class BasicDependencyStaticFactory {
    private static string $context = 'contextual';

    public static function change(string $context) : void {
        self::$context = $context;
    }

    public static function make(Container $container) : BasicDependency {
        if (Basic::CLASS === $container->context()) {
            return new BasicDependencyImpl('basic');
        }
        return new BasicDependencyImpl(self::$context);
    }

    public static function remake(string $value) : BasicDependency {
        return new BasicDependencyImpl($value);
    }
}

class BasicDependencyWrapper {
    private string $value = ':instance';
    public function wrap(BasicDependency $dep) : BasicDependency {
        if ($dep instanceof BasicDependencyImpl) {
            $dep->name .= $this->value;
        }
        return $dep;
    }

    public function value(string $value) : void {
        $this->value = ':' . $value;
    }
}

class BasicDependencyStaticWrapper {
    private static string $value = ':static';
    public static function wrap(BasicDependency $dep) : BasicDependency {
        if ($dep instanceof BasicDependencyImpl) {
            $dep->name .= self::$value;
        }
        return $dep;
    }

    public static function value(string $value) : void {
        self::$value = ':' . $value;
    }
}

interface Tagged {}

class FirstTag implements Tagged {}
class SecondTag implements Tagged {}
class ThirdTag implements Tagged {}
