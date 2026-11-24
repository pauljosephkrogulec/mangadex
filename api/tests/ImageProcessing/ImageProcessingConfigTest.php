<?php

declare(strict_types=1);

namespace App\Tests\ImageProcessing;

use App\Config\YamlConfigLoader;
use App\ImageProcessing\ImageProcessingConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ImageProcessingConfigTest extends TestCase
{
    /**
     * Le fichier livré (config/app/image_processing.yaml) passe la validation.
     */
    public function testShippedConfigurationIsValid(): void
    {
        $config = ImageProcessingConfig::fromYaml(new YamlConfigLoader(__DIR__.'/../../config/app'));

        self::assertEquals(new ImageProcessingConfig(['webp', 'avif'], 80, 1600, [160, 320, 640], 30), $config);
    }

    public function testDefaultsAndNormalization(): void
    {
        self::assertSame(
            ['formats' => ['webp', 'avif'], 'quality' => 80, 'max_width' => 1600, 'thumbnail_widths' => [160, 640], 'timeout' => 30],
            $this->resolve(['formats' => ['webp', 'avif', 'webp'], 'thumbnail_widths' => [640, 160, 640]]),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'format non supporté' => [['formats' => ['gif']]];
        yield 'aucun format' => [['formats' => []]];
        yield 'qualité hors bornes' => [['quality' => 0]];
        yield 'qualité en texte' => [['quality' => '80']];
        yield 'largeur négative' => [['max_width' => -1]];
        yield 'miniature plus large que max_width' => [['max_width' => 800, 'thumbnail_widths' => [320, 800]]];
        yield 'timeout nul' => [['timeout' => 0]];
        yield 'option inconnue' => [['resize' => true]];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationIsRejected(array $options): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->resolve($options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>
     */
    private function resolve(array $options): array
    {
        $resolver = new OptionsResolver();
        ImageProcessingConfig::configureOptions($resolver);

        return $resolver->resolve($options);
    }
}
