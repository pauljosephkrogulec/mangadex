<?php

declare(strict_types=1);

namespace App\ImageProcessing;

use App\Config\InvalidConfigurationException;
use App\Config\YamlConfigLoader;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Réglages du traitement d'images, lus dans config/app/image_processing.yaml (issue #26).
 */
final readonly class ImageProcessingConfig
{
    public const string FILE = 'image_processing.yaml';
    public const array SUPPORTED_FORMATS = ['webp', 'avif', 'jpeg', 'png'];

    /**
     * @param non-empty-list<value-of<self::SUPPORTED_FORMATS>> $formats         formats de sortie, du préféré au repli
     * @param int<1, 100>                                       $quality
     * @param positive-int                                      $maxWidth
     * @param list<positive-int>                                $thumbnailWidths triées, sans doublon
     * @param positive-int                                      $timeout         secondes
     */
    public function __construct(
        public array $formats,
        public int $quality,
        public int $maxWidth,
        public array $thumbnailWidths,
        public int $timeout,
    ) {
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function fromYaml(YamlConfigLoader $loader): self
    {
        /** @var array{formats: non-empty-list<value-of<self::SUPPORTED_FORMATS>>, quality: int<1, 100>, max_width: positive-int, thumbnail_widths: list<positive-int>, timeout: positive-int} $config */
        $config = $loader->load(self::FILE, self::configureOptions(...));

        return new self($config['formats'], $config['quality'], $config['max_width'], $config['thumbnail_widths'], $config['timeout']);
    }

    public static function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'formats' => ['webp'],
                'quality' => 80,
                'max_width' => 1600,
                'thumbnail_widths' => [],
                'timeout' => 30,
            ])
            ->setAllowedTypes('formats', 'string[]')
            ->setAllowedValues('formats', self::areSupportedFormats(...))
            ->setNormalizer('formats', static fn (Options $options, array $formats): array => self::uniqueValues($formats))
            ->setAllowedTypes('quality', 'int')
            ->setAllowedValues('quality', static fn (int $quality): bool => $quality >= 1 && $quality <= 100)
            ->setAllowedTypes('max_width', 'int')
            ->setAllowedValues('max_width', static fn (int $width): bool => $width > 0)
            ->setAllowedTypes('thumbnail_widths', 'int[]')
            ->setAllowedValues('thumbnail_widths', self::arePositive(...))
            // Une miniature plus large que l'image d'origine n'a pas de sens.
            ->setNormalizer('thumbnail_widths', static function (Options $options, array $widths): array {
                $maxWidth = $options['max_width'];
                \assert(\is_int($maxWidth));
                $widths = self::uniqueValues($widths);
                sort($widths);

                foreach ($widths as $width) {
                    if (\is_int($width) && $width >= $maxWidth) {
                        throw new InvalidOptionsException(\sprintf('La miniature de %d px doit être plus étroite que "max_width" (%d px).', $width, $maxWidth));
                    }
                }

                return $widths;
            })
            ->setAllowedTypes('timeout', 'int')
            ->setAllowedValues('timeout', static fn (int $seconds): bool => $seconds >= 1 && $seconds <= 300)
        ;
    }

    /**
     * @param array<mixed> $formats
     */
    private static function areSupportedFormats(array $formats): bool
    {
        if ([] === $formats) {
            return false;
        }

        return array_all($formats, static fn ($format): bool => \in_array($format, self::SUPPORTED_FORMATS, true));
    }

    /**
     * @param array<mixed> $widths
     */
    private static function arePositive(array $widths): bool
    {
        return array_all($widths, static fn ($width): bool => \is_int($width) && $width > 0);
    }

    /**
     * Retire les doublons en gardant l'ordre (comparaison stricte).
     *
     * @param array<mixed> $values
     *
     * @return list<mixed>
     */
    private static function uniqueValues(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            if (!\in_array($value, $unique, true)) {
                $unique[] = $value;
            }
        }

        return $unique;
    }
}
