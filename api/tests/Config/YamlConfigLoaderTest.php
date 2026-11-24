<?php

declare(strict_types=1);

namespace App\Tests\Config;

use App\Config\InvalidConfigurationException;
use App\Config\YamlConfigLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class YamlConfigLoaderTest extends TestCase
{
    private YamlConfigLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new YamlConfigLoader(__DIR__.'/Fixtures');
    }

    public function testResolvesTheFileWithDefaults(): void
    {
        $config = $this->loader->load('valid.yaml', static function (OptionsResolver $resolver): void {
            $resolver->setRequired('name')->setAllowedTypes('name', 'string')->setDefault('enabled', true);
        });

        ksort($config);
        self::assertSame(['enabled' => true, 'name' => 'demo'], $config);
    }

    public function testUnknownOptionNamesTheFile(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/"valid\.yaml".*"name"/');

        $this->loader->load('valid.yaml', static function (OptionsResolver $resolver): void {
            $resolver->setDefault('title', null);
        });
    }

    public function testInvalidTypeIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/Configuration invalide dans "valid\.yaml"/');

        $this->loader->load('valid.yaml', static function (OptionsResolver $resolver): void {
            $resolver->setRequired('name')->setAllowedTypes('name', 'int');
        });
    }

    public function testBrokenYamlIsReported(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/"broken\.yaml" illisible/');

        $this->loader->load('broken.yaml', static function (OptionsResolver $resolver): void {
        });
    }

    public function testMissingFileIsReported(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/"missing\.yaml" illisible/');

        $this->loader->load('missing.yaml', static function (OptionsResolver $resolver): void {
        });
    }

    public function testRootMustBeAMapping(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/tableau associatif/');

        $this->loader->load('list.yaml', static function (OptionsResolver $resolver): void {
        });
    }
}
