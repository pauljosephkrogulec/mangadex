<?php

declare(strict_types=1);

namespace App\Tests\Slug;

use App\Entity\Genre;
use App\Entity\Series;
use App\Factory\GenreFactory;
use App\Factory\SeriesFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Slugs générés automatiquement et uniques (issue #33) : SlugListener + UniqueSlugger.
 */
final class UniqueSluggerTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function titles(): iterable
    {
        yield 'espaces et ponctuation' => ['One Piece !', 'one-piece'];
        yield 'accents' => ['L’Épée de Kayōko', 'l-epee-de-kayoko'];
        yield 'deux-points' => ['Re:Zero − Starting Life in Another World', 're-zero-starting-life-in-another-world'];
        yield 'aucun caractère utilisable' => ['!!!', 'series'];
    }

    #[DataProvider('titles')]
    public function testSlugIsDerivedFromTheTitle(string $title, string $expected): void
    {
        self::assertSame($expected, SeriesFactory::createOne(['title' => $title])->getSlug());
    }

    /**
     * Le découpage exact dépend de la version d'ICU : on vérifie seulement que le slug est translittéré,
     * pas qu'il retombe sur le repli.
     *
     * @return iterable<string, array{string, non-empty-string}>
     */
    public static function nonLatinTitles(): iterable
    {
        yield 'japonais' => ['進撃の巨人', 'jin-'];
        yield 'coréen' => ['나 혼자만 레벨업', 'na-'];
        yield 'chinois' => ['全职高手', 'quan-'];
    }

    /**
     * @param non-empty-string $prefix
     */
    #[DataProvider('nonLatinTitles')]
    public function testNonLatinTitlesAreTransliterated(string $title, string $prefix): void
    {
        $slug = (string) SeriesFactory::createOne(['title' => $title])->getSlug();

        self::assertMatchesRegularExpression(Series::SLUG_PATTERN, $slug);
        self::assertStringStartsWith($prefix, $slug);
    }

    public function testCollidingSlugsGetANumericSuffix(): void
    {
        $slugs = array_map(
            static fn (Series $series): ?string => $series->getSlug(),
            SeriesFactory::createMany(3, ['title' => 'Monster']),
        );

        self::assertSame(['monster', 'monster-2', 'monster-3'], $slugs);
    }

    public function testEntitiesPersistedInTheSameFlushDoNotCollide(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $first = new Series('Dragon Ball');
        $second = new Series('Dragon Ball');

        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->flush();

        self::assertSame('dragon-ball', $first->getSlug());
        self::assertSame('dragon-ball-2', $second->getSlug());
    }

    public function testSuffixFillsTheFirstFreeNumberAndIgnoresLookalikes(): void
    {
        SeriesFactory::createSequence([
            ['title' => 'Naruto', 'slug' => 'naruto'],
            ['title' => 'Naruto 3', 'slug' => 'naruto-3'],
            // Ne doivent pas compter : ni variante numérique de « naruto », ni le même préfixe.
            ['title' => 'Naruto Shippuden', 'slug' => 'naruto-shippuden'],
            ['title' => 'Narutos', 'slug' => 'narutos'],
        ]);

        self::assertSame('naruto-2', SeriesFactory::createOne(['title' => 'Naruto'])->getSlug());
    }

    public function testUniquenessIsCheckedPerEntityType(): void
    {
        GenreFactory::createOne(['name' => 'Action']);

        self::assertSame('action', SeriesFactory::createOne(['title' => 'Action'])->getSlug());
        self::assertSame('action-2', GenreFactory::createOne(['name' => 'Action'])->getSlug());
    }

    public function testExplicitSlugIsKept(): void
    {
        self::assertSame('opm', SeriesFactory::createOne(['title' => 'One-Punch Man', 'slug' => 'opm'])->getSlug());
    }

    public function testSlugDoesNotChangeWhenTheTitleChanges(): void
    {
        $series = SeriesFactory::createOne(['title' => 'Shingeki no Kyojin']);

        $series->setTitle('Attack on Titan');
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        self::assertSame('shingeki-no-kyojin', $series->getSlug());
        SeriesFactory::assert()->exists(['title' => 'Attack on Titan', 'slug' => 'shingeki-no-kyojin']);
    }

    public function testLongTitlesAreTruncatedWithoutATrailingDash(): void
    {
        $slug = (string) GenreFactory::createOne(['name' => str_repeat('abcd ', 50)])->getSlug();

        self::assertLessThanOrEqual(200, mb_strlen($slug));
        self::assertStringEndsNotWith('-', $slug);
        self::assertMatchesRegularExpression(Genre::SLUG_PATTERN, $slug);
    }
}
