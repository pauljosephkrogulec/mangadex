<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as OptionsResolverException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Charge un fichier YAML de config/app/ et le valide avec OptionsResolver (issue #26).
 *
 * Le YAML garde la configuration métier (importeurs, traitements d'images…) lisible et versionnée ;
 * OptionsResolver la valide : options requises, types, valeurs permises, défauts, normalisation.
 * Une erreur indique le fichier fautif et l'option en cause.
 */
final readonly class YamlConfigLoader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/config/app')]
        private string $configDir,
    ) {
    }

    /**
     * @param string                         $file      chemin relatif à config/app/, ex. `importers.yaml`
     * @param callable(OptionsResolver):void $configure déclare les options attendues
     *
     * @return array<mixed> configuration validée, défauts appliqués
     *
     * @throws InvalidConfigurationException
     */
    public function load(string $file, callable $configure): array
    {
        $path = $this->configDir.'/'.$file;

        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $e) {
            throw new InvalidConfigurationException(\sprintf('Fichier de configuration "%s" illisible : %s', $file, $e->getMessage()), $e->getCode(), previous: $e);
        }

        if (!\is_array($data) || ([] !== $data && array_is_list($data))) {
            throw new InvalidConfigurationException(\sprintf('Le fichier de configuration "%s" doit contenir un tableau associatif (clé: valeur).', $file));
        }

        $resolver = new OptionsResolver();
        $configure($resolver);

        try {
            return $resolver->resolve($data);
        } catch (OptionsResolverException $e) {
            throw new InvalidConfigurationException(\sprintf('Configuration invalide dans "%s" : %s', $file, $e->getMessage()), $e->getCode(), previous: $e);
        }
    }
}
