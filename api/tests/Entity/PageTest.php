<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Chapter;
use App\Entity\Page;
use App\Entity\Series;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Règles portées par l'entité Page (issue #30), sans base de données.
 */
final class PageTest extends TestCase
{
    private const string HASH = 'b94d27b9934d3e08a52e52d7da7dabfac484efe37a5380ee9088f7ace2efcde9';

    public function testPageWithoutSegmentsIsValid(): void
    {
        $page = $this->page(height: 1600);

        self::assertFalse($page->isSegmented());
        self::assertCount(0, $this->validator()->validate($page));
    }

    public function testSegmentsCoveringThePageAreValid(): void
    {
        $page = $this->page(height: 3000)->setSegments([
            ['image' => 'pages/a-1.webp', 'height' => 1280],
            ['image' => 'pages/a-2.webp', 'height' => 1280],
            ['image' => 'pages/a-3.webp', 'height' => 440],
        ]);

        self::assertTrue($page->isSegmented());
        self::assertCount(0, $this->validator()->validate($page));
    }

    /**
     * @return iterable<string, array{list<array{image: string, height: int}>, string}>
     */
    public static function invalidSegments(): iterable
    {
        yield 'hauteur totale trop petite' => [[['image' => 'a.webp', 'height' => 1000], ['image' => 'b.webp', 'height' => 1000]], 'segments'];
        yield 'hauteur totale trop grande' => [[['image' => 'a.webp', 'height' => 2000], ['image' => 'b.webp', 'height' => 1001]], 'segments'];
        yield 'segment sans image' => [[['image' => ' ', 'height' => 1500], ['image' => 'b.webp', 'height' => 1500]], 'segments[0].image'];
    }

    /**
     * @param list<array{image: string, height: int}> $segments
     */
    #[DataProvider('invalidSegments')]
    public function testInvalidSegmentsAreRejected(array $segments, string $path): void
    {
        $violations = $this->validator()->validate($this->page(height: 3000)->setSegments($segments));

        self::assertCount(1, $violations);
        self::assertSame($path, $violations->get(0)->getPropertyPath());
    }

    public function testSegmentWithANonPositiveHeightIsRejected(): void
    {
        $page = $this->page(height: 3000)->setSegments([
            ['image' => 'a.webp', 'height' => 3000],
            ['image' => 'b.webp', 'height' => 0],
        ]);

        $paths = array_map(static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(), iterator_to_array($this->validator()->validate($page)));

        self::assertSame(['segments[1].height'], $paths);
    }

    /**
     * @return iterable<string, array{int, int, int, string, string}>
     */
    public static function invalidPages(): iterable
    {
        yield 'position nulle' => [0, 1100, 1600, self::HASH, 'position'];
        yield 'largeur nulle' => [1, 0, 1600, self::HASH, 'width'];
        yield 'hauteur négative' => [1, 1100, -1, self::HASH, 'height'];
        yield 'empreinte trop courte' => [1, 1100, 1600, 'abc123', 'hash'];
        yield 'empreinte en majuscules' => [1, 1100, 1600, mb_strtoupper(self::HASH), 'hash'];
    }

    #[DataProvider('invalidPages')]
    public function testInvalidPageIsRejected(int $position, int $width, int $height, string $hash, string $path): void
    {
        $page = new Page($this->chapter(), $position, 'pages/a.webp', $width, $height, $hash);

        $violations = $this->validator()->validate($page);

        self::assertCount(1, $violations);
        self::assertSame($path, $violations->get(0)->getPropertyPath());
    }

    public function testReplacingTheImageResetsItsSegments(): void
    {
        $page = $this->page(height: 2000)->setSegments([['image' => 'old.webp', 'height' => 2000]]);

        $page->replaceImage('pages/new.webp', 900, 1400, str_repeat('0', 64));

        self::assertSame('pages/new.webp', $page->getImage());
        self::assertSame(900, $page->getWidth());
        self::assertSame(1400, $page->getHeight());
        self::assertSame(str_repeat('0', 64), $page->getHash());
        self::assertFalse($page->isSegmented());
    }

    private function page(int $height): Page
    {
        return new Page($this->chapter(), 1, 'pages/a.webp', 800, $height, self::HASH);
    }

    private function chapter(): Chapter
    {
        return new Chapter(new Series('Tower of God'), '1', 'fr');
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
