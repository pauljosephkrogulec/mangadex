<?php

declare(strict_types=1);

namespace App\Story;

use App\Enum\SeriesType;
use App\Factory\AuthorFactory;
use App\Factory\ChapterFactory;
use App\Factory\GenreFactory;
use App\Factory\PageFactory;
use App\Factory\SeriesFactory;
use App\Factory\TagFactory;
use Zenstruck\Foundry\Story;

/**
 * Jeu de données de dev, chargé par `make db-reset` (doctrine:fixtures:load → AppFixtures).
 */
final class AppStory extends Story
{
    private const array GENRES = ['Action', 'Aventure', 'Comédie', 'Drame', 'Fantasy', 'Horreur', 'Romance', 'Science-fiction', 'Slice of life', 'Sport'];

    private const array TAGS = ['Isekai', 'Arts martiaux', 'Vampires', 'Voyage dans le temps', 'Réincarnation', 'École', 'Mecha', 'Post-apocalyptique', 'Couleur intégrale', 'Adaptation animée'];

    public function build(): void
    {
        GenreFactory::createSequence(array_map(static fn (string $name): array => ['name' => $name], self::GENRES));
        TagFactory::createSequence(array_map(static fn (string $name): array => ['name' => $name], self::TAGS));
        AuthorFactory::createMany(20);

        // Plus d'une page (30 par défaut) pour voir la pagination.
        SeriesFactory::createMany(45, static fn (): array => [
            'authors' => AuthorFactory::randomRange(1, 2),
            'artists' => AuthorFactory::randomRange(0, 1),
            'genres' => GenreFactory::randomRange(1, 3),
            'tags' => TagFactory::randomRange(0, 4),
        ]);

        // Quelques chapitres, en français et en anglais, sur un tiers des séries.
        foreach (SeriesFactory::randomSet(15) as $series) {
            foreach (['fr', 'en'] as $language) {
                $chapters = ChapterFactory::createSequence(array_map(
                    static fn (int $number): array => ['series' => $series, 'language' => $language, 'number' => (string) $number],
                    range(1, random_int(3, 12)),
                ));

                // Pages : planches classiques, ou longues bandes segmentées pour les webtoons.
                $pages = SeriesType::Webtoon === $series->getType() ? PageFactory::new()->webtoonStrip() : PageFactory::new();
                foreach ($chapters as $chapter) {
                    $pages->sequence(array_map(
                        static fn (int $position): array => ['chapter' => $chapter, 'position' => $position],
                        range(1, SeriesType::Webtoon === $series->getType() ? 3 : random_int(4, 8)),
                    ))->create();
                }
            }
        }
    }
}
