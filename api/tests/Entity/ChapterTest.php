<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Chapter;
use App\Entity\Series;
use App\Enum\ChapterStatus;
use App\Validator\UniqueChapterNumberValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Règles portées par l'entité Chapter (issue #29), sans base de données.
 */
final class ChapterTest extends TestCase
{
    public function testNewChapterIsADraft(): void
    {
        $chapter = $this->chapter('1', 'fr');

        self::assertSame(ChapterStatus::Draft, $chapter->getStatus());
        self::assertNull($chapter->getPublishedAt());
        self::assertCount(0, $this->validator()->validate($chapter));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function numbers(): iterable
    {
        yield 'entier' => ['12', '12'];
        yield 'entier venant de PostgreSQL' => ['12.00', '12'];
        yield 'demi-chapitre' => ['10.50', '10.5'];
        yield 'deux décimales' => ['3.25', '3.25'];
        yield 'zéro' => ['0.00', '0'];
        yield 'dizaine' => ['100', '100'];
    }

    #[DataProvider('numbers')]
    public function testNumberIsExposedInCanonicalForm(string $stored, string $expected): void
    {
        self::assertSame($expected, $this->chapter($stored, 'fr')->getNumber());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidChapters(): iterable
    {
        yield 'numéro négatif' => ['-1', 'fr', 'number'];
        yield 'numéro avec trois décimales' => ['1.125', 'fr', 'number'];
        yield 'numéro non numérique' => ['un', 'fr', 'number'];
        yield 'langue en majuscules' => ['1', 'FR', 'language'];
        yield 'langue trop longue' => ['1', 'french', 'language'];
        yield 'région en minuscules' => ['1', 'pt-br', 'language'];
    }

    #[DataProvider('invalidChapters')]
    public function testInvalidNumberOrLanguageIsRejected(string $number, string $language, string $path): void
    {
        $violations = $this->validator()->validate($this->chapter($number, $language));

        self::assertCount(1, $violations);
        self::assertSame($path, $violations->get(0)->getPropertyPath());
    }

    public function testRegionalLanguageIsAccepted(): void
    {
        self::assertCount(0, $this->validator()->validate($this->chapter('1', 'pt-BR')));
    }

    /**
     * @return iterable<string, array{ChapterStatus}>
     */
    public static function publishedStatuses(): iterable
    {
        yield 'programmé' => [ChapterStatus::Scheduled];
        yield 'publié' => [ChapterStatus::Published];
    }

    #[DataProvider('publishedStatuses')]
    public function testPublishedOrScheduledChapterRequiresAPublicationDate(ChapterStatus $status): void
    {
        $chapter = $this->chapter('1', 'fr')->setStatus($status);

        $violations = $this->validator()->validate($chapter);
        self::assertCount(1, $violations);
        self::assertSame('publishedAt', $violations->get(0)->getPropertyPath());

        $chapter->setPublishedAt(new \DateTimeImmutable('2026-01-01'));
        self::assertCount(0, $this->validator()->validate($chapter));
    }

    public function testVolumeMustBePositive(): void
    {
        $violations = $this->validator()->validate($this->chapter('1', 'fr')->setVolume(0));

        self::assertCount(1, $violations);
        self::assertSame('volume', $violations->get(0)->getPropertyPath());
    }

    private function chapter(string $number, string $language): Chapter
    {
        return new Chapter(new Series('Berserk'), $number, $language);
    }

    /**
     * Sans base de données : l'unicité du numéro (UniqueChapterNumber) est testée dans
     * App\Tests\Validator\UniqueChapterNumberValidatorTest.
     */
    private function validator(): ValidatorInterface
    {
        $withoutDatabase = new class extends ConstraintValidator {
            public function validate(mixed $value, Constraint $constraint): void
            {
            }
        };

        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([UniqueChapterNumberValidator::class => $withoutDatabase]))
            ->getValidator();
    }
}
