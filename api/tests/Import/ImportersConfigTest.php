<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Config\InvalidConfigurationException;
use App\Config\YamlConfigLoader;
use App\Import\ImporterConfig;
use App\Import\ImportersConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ImportersConfigTest extends TestCase
{
    private const string CONFIG_DIR = __DIR__.'/../../config/app';

    /**
     * Le fichier livré (config/app/importers.yaml) passe la validation.
     */
    public function testShippedConfigurationIsValid(): void
    {
        $config = new ImportersConfig(new YamlConfigLoader(self::CONFIG_DIR));

        $mangadex = $config->get('mangadex');
        self::assertEquals(new ImporterConfig('mangadex', true, 'https://api.mangadex.org', 5.0, 10, ['fr', 'en']), $mangadex);
        self::assertSame(['mangadex'], array_keys($config->enabled()));
    }

    public function testUnknownImporterIsReported(): void
    {
        $config = new ImportersConfig(new YamlConfigLoader(self::CONFIG_DIR));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Importeur "kitsu" inconnu (sources déclarées : mangadex, anilist).');

        $config->get('kitsu');
    }

    public function testDefaultsAndNormalization(): void
    {
        $resolved = $this->resolve(['src' => ['base_url' => 'https://example.org/api/', 'requests_per_second' => 2]]);

        self::assertSame([
            'enabled' => true,
            'requests_per_second' => 2.0,
            'timeout' => 10,
            'languages' => ['fr', 'en'],
            'base_url' => 'https://example.org/api',
        ], $resolved['src']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidImporters(): iterable
    {
        yield 'base_url manquante' => [[]];
        yield 'base_url en HTTP' => [['base_url' => 'http://example.org']];
        yield 'base_url invalide' => [['base_url' => 'https://']];
        yield 'débit nul' => [['base_url' => 'https://example.org', 'requests_per_second' => 0]];
        yield 'timeout trop long' => [['base_url' => 'https://example.org', 'timeout' => 120]];
        yield 'langue non ISO 639-1' => [['base_url' => 'https://example.org', 'languages' => ['french']]];
        yield 'aucune langue' => [['base_url' => 'https://example.org', 'languages' => []]];
        yield 'option inconnue' => [['base_url' => 'https://example.org', 'api_key' => 'secret']];
        yield 'enabled non booléen' => [['base_url' => 'https://example.org', 'enabled' => 'yes']];
    }

    /**
     * @param array<string, mixed> $importer
     */
    #[DataProvider('invalidImporters')]
    public function testInvalidImporterIsRejected(array $importer): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->resolve(['src' => $importer]);
    }

    /**
     * @param array<string, mixed> $importers
     *
     * @return array<mixed>
     */
    private function resolve(array $importers): array
    {
        $resolver = new OptionsResolver();
        ImportersConfig::configureOptions($resolver);
        $resolved = $resolver->resolve(['importers' => $importers])['importers'];
        self::assertIsArray($resolved);

        return $resolved;
    }
}
