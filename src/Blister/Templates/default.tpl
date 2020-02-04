<?= '<?php declare(strict_types=1);' . PHP_EOL; ?>
<?php if ($namespace): ?>

namespace <?= $namespace ?>;
<?php endif; ?>

/**
 * Generated at: <?= \date(DATE_ATOM); ?>.
 *
 * DO NOT EDIT!!!
 */
final class <?= $container ?> extends \Bitnix\Service\Blister\Injector {

    private array $services = <?= $services->render(); ?>;

    private array $wrappers = <?= $wrappers->render(); ?>;

    public function __construct() {
        parent::__construct(
            // aliases
    <?= $aliases->render(3); ?>,

            // prototypes
    <?= $prototypes->render(3); ?>,

            // tags
    <?= $tags->render(3); ?>

        );
    }

    protected function service(string $fqcn) : ?object {
        if (isset($this->services[$fqcn])) {
            $method = $this->services[$fqcn];
            return $this->$method();
        }
       return null;
    }

    protected function wrap(string $fqcn, object $object) : object {
        foreach ($this->wrappers[$fqcn] ?? [] as $wrap) {
            $object = $this->$wrap($object);
        }
        return $object;
    }

<?php foreach ($methods as $method): ?>
<?= $method->render(); ?>


<?php endforeach; ?>
}
