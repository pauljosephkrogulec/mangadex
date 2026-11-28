<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Series;
use App\Enum\SeriesStatus;
use App\Enum\SeriesType;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Fabrique Foundry (fixtures et tests). Dev/test uniquement : Foundry n'est pas installé en prod.
 *
 * @extends PersistentObjectFactory<Series>
 */
final class SeriesFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Series::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'title' => mb_rtrim(self::faker()->unique()->sentence(3), '.'),
            'alternativeTitles' => self::faker()->optional(0.5, [])->words(2),
            'synopsis' => self::faker()->optional(0.8)->paragraph(),
            'type' => self::faker()->randomElement(SeriesType::cases()),
            'status' => self::faker()->randomElement(SeriesStatus::cases()),
            'year' => self::faker()->optional(0.9)->numberBetween(1970, 2026),
            'coverImage' => self::faker()->boolean(70) ? 'covers/'.self::faker()->uuid().'.webp' : null,
        ];
    }

    protected function initialize(): static
    {
        // Provisoire : le slug sera généré par le service de slug avec #33. D'ici là, il est
        // dérivé du titre quand le test ne le fixe pas.
        return $this->beforeInstantiate(static function (array $parameters): array {
            if (!isset($parameters['slug']) && \is_string($parameters['title'] ?? null)) {
                $parameters['slug'] = new AsciiSlugger()->slug($parameters['title'])->lower()->toString();
            }

            return $parameters;
        });
    }
}
