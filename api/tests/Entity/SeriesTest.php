<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Author;
use App\Entity\Genre;
use App\Entity\Series;
use App\Entity\Tag;
use App\Enum\SeriesType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Règles portées par l'entité Series (issues #27 et #28), sans base de données.
 */
final class SeriesTest extends TestCase
{
    public function testDefaultsToAnOngoingMangaWithoutAlternativeTitles(): void
    {
        $series = new Series('Berserk');

        self::assertSame(SeriesType::Manga, $series->getType());
        self::assertSame([], $series->getAlternativeTitles());
        self::assertNull($series->getYear());
        self::assertNull($series->getCoverImage());
    }

    public function testAlternativeTitlesAreTrimmedDeduplicatedAndExcludeTheMainTitle(): void
    {
        $series = new Series('Berserk');

        $series->setAlternativeTitles(['  ベルセルク ', '', 'berserk', 'Berserk: The Black Swordsman', 'ベルセルク', '   ']);

        self::assertSame(['ベルセルク', 'Berserk: The Black Swordsman'], $series->getAlternativeTitles());
    }

    public function testRenamingRemovesTheNewTitleFromAlternativeTitles(): void
    {
        $series = new Series('Shingeki no Kyojin');
        $series->setAlternativeTitles(['Attack on Titan', "L'Attaque des Titans"]);

        $series->setTitle('Attack on Titan');

        self::assertSame(["L'Attaque des Titans"], $series->getAlternativeTitles());
    }

    public function testRelationsIgnoreDuplicatesAndCanBeRemoved(): void
    {
        $series = new Series('Berserk');
        $miura = new Author('Kentarō Miura');
        $fantasy = new Genre('Dark fantasy');
        $mercenaries = new Tag('Mercenaires');

        $series->addAuthor($miura)->addAuthor($miura)->addArtist($miura);
        $series->addGenre($fantasy)->addGenre($fantasy);
        $series->addTag($mercenaries);

        self::assertCount(1, $series->getAuthors());
        self::assertSame([$miura], $series->getArtists()->getValues());
        self::assertCount(1, $series->getGenres());

        $series->removeTag($mercenaries)->removeArtist($miura);

        self::assertCount(0, $series->getTags());
        self::assertCount(0, $series->getArtists());
        self::assertCount(1, $series->getAuthors(), 'Retirer un artiste ne retire pas l\'auteur.');
    }

    public function testValidSeriesHasNoViolation(): void
    {
        $series = new Series('Berserk')->setYear(1989)->setAlternativeTitles(['ベルセルク']);

        self::assertCount(0, $this->validator()->validate($series));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSlugs(): iterable
    {
        yield 'majuscules' => ['Berserk'];
        yield 'espace' => ['ber serk'];
        yield 'tiret final' => ['berserk-'];
        yield 'double tiret' => ['ber--serk'];
        yield 'vide' => [''];
    }

    #[DataProvider('invalidSlugs')]
    public function testInvalidSlugIsRejected(string $slug): void
    {
        $violations = $this->validator()->validate(new Series('Berserk')->setSlug($slug));

        self::assertGreaterThan(0, \count($violations));
        self::assertSame('slug', $violations->get(0)->getPropertyPath());
    }

    public function testYearOutOfRangeIsRejected(): void
    {
        $violations = $this->validator()->validate(new Series('Berserk')->setYear(1850));

        self::assertCount(1, $violations);
        self::assertSame('year', $violations->get(0)->getPropertyPath());
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
