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

use Throwable,
    Bitnix\Service\ConfigurationError;

/**
 * @version 0.1.0
 */
final class Template implements Compiler {

    public const DEFAULT
        = __DIR__ . '/Templates/default.tpl';

    private const VALID_CLASS
        = '~^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff\\\\]*$~';

    /**
     * @var array
     */
    private array $services = [];

    /**
     * @var array
     */
    private array $wrappers = [];

    /**
     * @var array
     */
    private array $methods = [];

    /**
     * @var array
     */
    private array $prototypes = [];

    /**
     * @var array
     */
    private array $aliases = [];

    /**
     * @var array
     */
    private array $tags = [];

    /**
     * @var array
     */
    private array $data = [];

    /**
     * @param Provider $provider
     */
    public function provider(Provider $provider) : void {
        $fqcn = $provider->binding();

        foreach ($provider->aliases() as $alias) {
            $this->aliases[$alias] = $fqcn;
        }

        if ($provider->prototype()) {
            $this->prototypes[$fqcn] = true;
        }

        foreach ($provider->tags() as $tag) {
            $name = $tag->name();
            $this->tags[$name] ??= [];
            $this->tags[$name][] = [$tag->priority(), $fqcn];
        }

        $name = $this->method($fqcn, 'Provider');
        $this->services[$fqcn] = $name;
        $this->methods[] = $provider->handler($name);
    }

    /**
     * @param string $fqcn
     * @param string $suffix
     * @return string
     */
    private function method(string $fqcn, string $suffix) : string {
        return \lcfirst(\implode(
            '',
            \array_map(
                fn($el) => \ucfirst($el),
                \explode(' ', \str_replace('\\', ' ', $fqcn))
            )
        ) . $suffix);
    }

    /**
     * @param Wrapper $wrapper
     */
    public function wrapper(Wrapper $wrapper) : void {
        $fqcn = $wrapper->wrapping();

        $this->wrappers[$fqcn] ??= [];

        $name = $this->method($wrapper->binding(), 'Wrapper');
        $this->methods[] = $wrapper->handler($name);
        $this->wrappers[$fqcn][] = [$wrapper->priority(), $name];
    }

    /**
     * @return Collection
     */
    private function tags() : Collection {
        $sorted = [];
        foreach ($this->tags as $tag => $info) {
            \usort($info, fn($a, $b) => $b[0] <=> $a[0]);
            $sorted[$tag] = \array_unique(
                \array_map(fn($el) => $el[1], $info)
            );
        }
        return new Collection($sorted);
    }

    /**
     * @return Collection
     */
    private function wrappers() : Collection {
        $sorted = [];
        foreach ($this->wrappers as $fqcn => $info) {
            \usort($info, fn($a, $b) => $b[0] <=> $a[0]);
            $sorted[$fqcn] = \array_unique(
                \array_map(fn($el) => $el[1], $info)
            );
        }
        return new Collection($sorted);
    }

    /**
     * @param string $__file__
     * @param array $__model__
     * @return string
     */
    private function process(string $__file__, array $__model__) : string {
        \extract($__model__, \EXTR_SKIP);
        \ob_start();
        include $__file__;
        return \ob_get_clean();
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function get(string $key, $default = null) {
        return $this->data[$key] ?? $default;
    }

    /**
     * @param string $fqcn
     * @param string $template
     * @param array $data
     * @return string
     * @throws ConfigurationError
     */
    public function render(string $fqcn, string $template = self::DEFAULT, array $data = []) : string {

        if (!\preg_match(self::VALID_CLASS, $fqcn)) {
            throw new ConfigurationError(\sprintf(
                'Invalid container class name: "%s"', $fqcn
            ));
        }

        if (!\is_file($template) || !\is_readable($template)) {
            throw new ConfigurationError(\sprintf(
                'Unable to find or read compiler template: "%s"', $template
            ));
        }

        try {

            $this->data = $data;
            $level = \ob_get_level();

            \set_error_handler(function($error, $message, $file, $line) {
                if (0 !== \error_reporting()) {
                    throw new ConfigurationError(\sprintf(
                        '%s, at %s (%d)', $message, $file, $line
                    ));
                }

                return false;
            });

            $namespace = null;
            if (false !== ($split = \strrpos($fqcn, '\\'))) {
                $namespace = \substr($fqcn, 0, $split);
                $fqcn = \substr($fqcn, $split + 1);
            }

            return $this->process(
                $template,
                [
                    'namespace'  => $namespace,
                    'container'  => $fqcn,
                    'services'   => new Collection($this->services),
                    'aliases'    => new Collection($this->aliases),
                    'prototypes' => new Collection($this->prototypes),
                    'tags'       => $this->tags(),
                    'wrappers'   => $this->wrappers(),
                    'methods'    => $this->methods
                ]
            );
        } catch (Throwable $x) {
            if (!($x instanceof ConfigurationError)) {
                $x = new ConfigurationError($x->getMessage());
            }

            while (\ob_get_level() > $level) {
                \ob_end_clean();
            }

            throw $x;
        } finally {
            \restore_error_handler();
            $this->services
                = $this->wrappers
                = $this->methods
                = $this->prototypes
                = $this->aliases
                = $this->tags
                = $this->data
                = [];
        }
    }

    /**
     * @return string
     */
    public function __toString() : string {
        return self::CLASS;
    }
}
