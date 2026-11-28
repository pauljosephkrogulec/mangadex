<?php

declare(strict_types=1);

namespace App\Tests\HtmlSanitizer;

use App\Entity\Series;
use App\Factory\SeriesFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Synopsis nettoyés avant l'écriture en base (issue #35) : HtmlSanitizer + HtmlSanitizerListener.
 */
final class SynopsisSanitizingTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function payloads(): iterable
    {
        yield 'script retiré avec son contenu' => ['<p>Résumé</p><script>alert(1)</script>', '<p>Résumé</p>'];
        yield 'gestionnaire d’événement' => ['<p onclick="alert(1)">Résumé</p>', '<p>Résumé</p>'];
        yield 'image avec onerror' => ['<img src=x onerror=alert(1)>Résumé', 'Résumé'];
        yield 'lien javascript:' => ['<a href="javascript:alert(1)">clic</a>', '<a rel="noopener noreferrer nofollow ugc">clic</a>'];
        yield 'lien data:' => ['<a href="data:text/html,<script>alert(1)</script>">clic</a>', '<a rel="noopener noreferrer nofollow ugc">clic</a>'];
        yield 'iframe' => ['<iframe src="https://evil.example"></iframe><p>Ok</p>', '<p>Ok</p>'];
        yield 'style' => ['<style>body{display:none}</style><p style="color:red">Ok</p>', '<p>Ok</p>'];
        yield 'svg' => ['<svg onload=alert(1)><circle/></svg>Ok', 'Ok'];
        yield 'entièrement malveillant' => ['<script>alert(1)</script>', null];
        yield 'espaces seulement' => ["  \n ", null];
    }

    #[DataProvider('payloads')]
    public function testDangerousHtmlIsRemoved(string $synopsis, ?string $expected): void
    {
        self::assertSame($expected, $this->persistedSynopsis($synopsis));
    }

    public function testAllowedFormattingIsKept(): void
    {
        $html = '<p><strong>Guts</strong>, un <em>mercenaire</em> :</p><ul><li>épéiste</li><li>solitaire</li></ul><blockquote>Lutte.</blockquote>';

        self::assertSame($html, $this->persistedSynopsis($html));
    }

    public function testExternalLinksAreForcedToHttpsAndNofollow(): void
    {
        self::assertSame(
            '<p>Voir <a href="https://fr.wikipedia.org/wiki/Berserk" rel="noopener noreferrer nofollow ugc">Wikipédia</a></p>',
            $this->persistedSynopsis('<p>Voir <a href="http://fr.wikipedia.org/wiki/Berserk" target="_blank">Wikipédia</a></p>'),
        );
    }

    public function testPlainTextIsEscapedNotInterpreted(): void
    {
        self::assertSame('Tom &amp; Jerry &lt;3', $this->persistedSynopsis('Tom & Jerry <3'));
    }

    public function testSynopsisIsSanitizedOnUpdateToo(): void
    {
        $series = SeriesFactory::createOne(['synopsis' => '<p>Propre</p>']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $series->setSynopsis('<p>Modifié</p><script>alert(1)</script>');
        $entityManager->flush();

        self::assertSame('<p>Modifié</p>', $this->synopsisInDatabase($series));
    }

    public function testSynopsisChangedBetweenPersistAndFlushIsSanitized(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $series = new Series('Berserk');

        $entityManager->persist($series);
        $series->setSynopsis('<p>Tardif</p><script>alert(1)</script>');
        $entityManager->flush();

        self::assertSame('<p>Tardif</p>', $this->synopsisInDatabase($series));
    }

    public function testNullSynopsisStaysNull(): void
    {
        self::assertNull(SeriesFactory::createOne(['synopsis' => null])->getSynopsis());
    }

    private function persistedSynopsis(string $synopsis): ?string
    {
        return $this->synopsisInDatabase(SeriesFactory::createOne(['synopsis' => $synopsis]));
    }

    /**
     * Relu en SQL : vérifie ce qui est réellement écrit, pas seulement l'objet en mémoire.
     */
    private function synopsisInDatabase(Series $series): ?string
    {
        $synopsis = self::getContainer()->get(EntityManagerInterface::class)->getConnection()
            ->fetchOne('SELECT synopsis FROM series WHERE id = ?', [$series->getId()?->toRfc4122()]);
        self::assertTrue(null === $synopsis || \is_string($synopsis));

        return $synopsis;
    }
}
