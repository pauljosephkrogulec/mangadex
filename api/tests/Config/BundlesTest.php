<?php

declare(strict_types=1);

namespace App\Tests\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;

/**
 * Un bundle activé en prod (`all` ou `prod` dans config/bundles.php) doit venir d'un paquet installé
 * en prod : l'image Docker fait `composer install --no-dev`, un bundle issu de `require-dev` y serait
 * absent et le kernel ne démarrerait pas.
 *
 * Cas réel : la recette Flex de Twig active TwigBundle partout, alors qu'il n'arrivait qu'en dépendance
 * (dev) du Web Profiler.
 */
final class BundlesTest extends TestCase
{
    private const string PROJECT_DIR = __DIR__.'/../..';

    /**
     * @return iterable<class-string, array{class-string}>
     */
    public static function prodBundles(): iterable
    {
        /** @var array<class-string, array<string, bool>> $bundles */
        $bundles = require self::PROJECT_DIR.'/config/bundles.php';

        foreach ($bundles as $class => $envs) {
            if (($envs['all'] ?? false) || ($envs['prod'] ?? false)) {
                yield $class => [$class];
            }
        }
    }

    /**
     * @param class-string $bundleClass
     */
    #[DataProvider('prodBundles')]
    public function testProdBundleComesFromAProdDependency(string $bundleClass): void
    {
        $package = $this->packageOf($bundleClass);

        self::assertNotContains(
            $package,
            $this->devOnlyPackages(),
            \sprintf('%s est activé en prod mais "%s" n\'est installé qu\'en dev : déclare-le dans "require".', $bundleClass, $package),
        );
    }

    public function testDevToolsAreNeverEnabledInProd(): void
    {
        $prodBundles = array_keys(iterator_to_array(self::prodBundles()));

        self::assertNotContains(DebugBundle::class, $prodBundles);
        self::assertNotContains(MakerBundle::class, $prodBundles);
        self::assertNotContains(WebProfilerBundle::class, $prodBundles);
    }

    /**
     * @param class-string $class
     */
    private function packageOf(string $class): string
    {
        $file = (string) new \ReflectionClass($class)->getFileName();
        self::assertMatchesRegularExpression('#/vendor/[^/]+/[^/]+/#', $file, \sprintf('%s ne vient pas de vendor/.', $class));
        preg_match('#/vendor/([^/]+/[^/]+)/#', $file, $matches);

        return $matches[1] ?? '';
    }

    /**
     * @return list<string>
     */
    private function devOnlyPackages(): array
    {
        $installed = json_decode(
            (string) file_get_contents(self::PROJECT_DIR.'/vendor/composer/installed.json'),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($installed);
        self::assertArrayHasKey('dev-package-names', $installed);
        self::assertIsList($installed['dev-package-names']);

        $names = [];
        foreach ($installed['dev-package-names'] as $name) {
            self::assertIsString($name);
            $names[] = $name;
        }

        return $names;
    }
}
