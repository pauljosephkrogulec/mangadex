<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Entity\Chapter;
use App\Entity\Series;
use App\Factory\ChapterFactory;
use App\Factory\SeriesFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Numéro de chapitre unique par série et par langue (issue #32), vérifié avant l'écriture en base.
 */
final class UniqueChapterNumberValidatorTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    /**
     * @return iterable<string, array{string}>
     */
    public static function sameNumbers(): iterable
    {
        yield 'même écriture' => ['12'];
        yield 'avec des décimales nulles' => ['12.00'];
        yield 'avec une décimale nulle' => ['12.0'];
    }

    #[DataProvider('sameNumbers')]
    public function testDuplicateNumberInTheSameSeriesAndLanguageIsRejected(string $number): void
    {
        $series = SeriesFactory::createOne();
        ChapterFactory::createOne(['series' => $series, 'number' => '12', 'language' => 'fr']);

        $violations = $this->validate(new Chapter($series, $number, 'fr'));

        self::assertCount(1, $violations);
        self::assertSame('number', $violations->get(0)->getPropertyPath());
        self::assertSame('Le chapitre 12 existe déjà en « fr » pour cette série.', $violations->get(0)->getMessage());
    }

    public function testSameNumberInAnotherLanguageIsAccepted(): void
    {
        $series = SeriesFactory::createOne();
        ChapterFactory::createOne(['series' => $series, 'number' => '12', 'language' => 'fr']);

        self::assertCount(0, $this->validate(new Chapter($series, '12', 'en')));
    }

    public function testSameNumberInAnotherSeriesIsAccepted(): void
    {
        ChapterFactory::createOne(['number' => '12', 'language' => 'fr']);

        self::assertCount(0, $this->validate(new Chapter(SeriesFactory::createOne(), '12', 'fr')));
    }

    public function testCloseButDifferentNumberIsAccepted(): void
    {
        $series = SeriesFactory::createOne();
        ChapterFactory::createOne(['series' => $series, 'number' => '12', 'language' => 'fr']);

        self::assertCount(0, $this->validate(new Chapter($series, '12.5', 'fr')));
    }

    public function testAChapterDoesNotConflictWithItself(): void
    {
        $chapter = ChapterFactory::createOne(['number' => '12', 'language' => 'fr']);

        self::assertCount(0, $this->validate($chapter->setTitle('Nouveau titre')));
    }

    public function testChangingTheNumberOfAChapterToATakenOneIsRejected(): void
    {
        $series = SeriesFactory::createOne();
        ChapterFactory::createOne(['series' => $series, 'number' => '1', 'language' => 'fr']);
        $second = ChapterFactory::createOne(['series' => $series, 'number' => '2', 'language' => 'fr']);

        self::assertCount(1, $this->validate($second->setNumber('1')));
    }

    public function testChapterOfANewSeriesIsAccepted(): void
    {
        self::assertCount(0, $this->validate(new Chapter(new Series('Inédit'), '1', 'fr')));
    }

    private function validate(Chapter $chapter): ConstraintViolationListInterface
    {
        return self::getContainer()->get(ValidatorInterface::class)->validate($chapter);
    }
}
