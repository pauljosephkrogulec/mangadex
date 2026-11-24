<?php

declare(strict_types=1);

namespace App\Import;

use App\Config\InvalidConfigurationException;
use App\Config\YamlConfigLoader;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Sources d'import déclarées dans config/app/importers.yaml (issue #26).
 */
final class ImportersConfig
{
    public const string FILE = 'importers.yaml';

    /** @var array<string, ImporterConfig>|null */
    private ?array $importers = null;

    public function __construct(
        private readonly YamlConfigLoader $loader,
    ) {
    }

    public static function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('importers')
            ->setAllowedTypes('importers', 'array')
            ->setOptions('importers', static function (OptionsResolver $importer): void {
                // Chaque clé de `importers` est une source ; ses options sont validées une à une.
                $importer
                    ->setPrototype(true)
                    ->setDefaults([
                        'enabled' => true,
                        'requests_per_second' => 1.0,
                        'timeout' => 10,
                        'languages' => ['fr', 'en'],
                    ])
                    ->setRequired('base_url')
                    ->setAllowedTypes('enabled', 'bool')
                    ->setAllowedTypes('base_url', 'string')
                    ->setAllowedValues('base_url', static fn (string $url): bool => str_starts_with($url, 'https://') && false !== filter_var($url, \FILTER_VALIDATE_URL))
                    ->setNormalizer('base_url', static fn (Options $options, string $url): string => mb_rtrim($url, '/'))
                    ->setAllowedTypes('requests_per_second', ['int', 'float'])
                    ->setAllowedValues('requests_per_second', static fn (int|float $rate): bool => $rate > 0 && $rate <= 50)
                    ->setNormalizer('requests_per_second', static fn (Options $options, int|float $rate): float => (float) $rate)
                    ->setAllowedTypes('timeout', 'int')
                    ->setAllowedValues('timeout', static fn (int $seconds): bool => $seconds >= 1 && $seconds <= 60)
                    ->setAllowedTypes('languages', 'string[]')
                    ->setAllowedValues('languages', self::areLanguageCodes(...))
                ;
            })
        ;
    }

    /**
     * @return array<string, ImporterConfig>
     *
     * @throws InvalidConfigurationException
     */
    public function all(): array
    {
        return $this->importers ??= $this->build();
    }

    /**
     * @return array<string, ImporterConfig>
     */
    public function enabled(): array
    {
        return array_filter($this->all(), static fn (ImporterConfig $importer): bool => $importer->enabled);
    }

    public function get(string $name): ImporterConfig
    {
        return $this->all()[$name] ?? throw new InvalidConfigurationException(\sprintf('Importeur "%s" inconnu (sources déclarées : %s).', $name, implode(', ', array_keys($this->all()))));
    }

    /**
     * Liste non vide de codes ISO 639-1 (`fr`, `en`…).
     *
     * @param array<mixed> $languages
     */
    private static function areLanguageCodes(array $languages): bool
    {
        if ([] === $languages) {
            return false;
        }

        return array_all($languages, static fn ($language): bool => \is_string($language) && 1 === preg_match('/^[a-z]{2}$/', $language));
    }

    /**
     * @return array<string, ImporterConfig>
     */
    private function build(): array
    {
        $config = $this->loader->load(self::FILE, self::configureOptions(...));

        /** @var array<non-empty-string, array{enabled: bool, base_url: non-empty-string, requests_per_second: float, timeout: int, languages: list<non-empty-string>}> $importers */
        $importers = $config['importers'];

        $result = [];
        foreach ($importers as $name => $options) {
            $result[$name] = new ImporterConfig(
                name: $name,
                enabled: $options['enabled'],
                baseUrl: $options['base_url'],
                requestsPerSecond: $options['requests_per_second'],
                timeout: $options['timeout'],
                languages: $options['languages'],
            );
        }

        return $result;
    }
}
