<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Page;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Fabrique Foundry (fixtures et tests). Dev/test uniquement : Foundry n'est pas installé en prod.
 *
 * @extends PersistentObjectFactory<Page>
 */
final class PageFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Page::class;
    }

    /**
     * Bande verticale de webtoon : 800 px de large, découpée en segments de 1 280 px au plus.
     */
    public function webtoonStrip(int $height = 6000): static
    {
        $segments = [];
        for ($offset = 0, $index = 1; $offset < $height; $offset += 1280, ++$index) {
            $segments[] = ['image' => \sprintf('pages/%s-%d.webp', self::faker()->uuid(), $index), 'height' => min(1280, $height - $offset)];
        }

        return $this->with(['width' => 800, 'height' => $height, 'segments' => $segments]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'chapter' => ChapterFactory::new(),
            'position' => self::faker()->unique()->numberBetween(1, 30000),
            'image' => 'pages/'.self::faker()->uuid().'.webp',
            'width' => 1100,
            'height' => 1600,
            'hash' => hash('sha256', self::faker()->uuid()),
        ];
    }
}
