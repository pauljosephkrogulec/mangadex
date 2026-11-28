<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Factory\ChapterFactory;
use App\Factory\SeriesFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * API multilingue (issue #34) : locale négociée via Accept-Language, messages et noms de langue traduits.
 */
final class LocaleNegotiationTest extends WebTestCase
{
    use ApiTestHelpers;
    use Factories;
    use ResetDatabase;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function acceptLanguages(): iterable
    {
        yield 'français' => ['fr', 'fr'];
        yield 'anglais' => ['en', 'en'];
        yield 'variante régionale' => ['en-GB,en;q=0.9', 'en'];
        yield 'préférences pondérées' => ['de;q=1.0, en;q=0.8, fr;q=0.5', 'en'];
        yield 'langue non prise en charge' => ['de', 'fr'];
        yield 'aucune préférence' => ['', 'fr'];
    }

    #[DataProvider('acceptLanguages')]
    public function testLocaleIsNegotiatedAndAnnouncedInContentLanguage(string $acceptLanguage, string $expected): void
    {
        $client = self::createClient();

        $this->request($client, 'GET', '/api/series', language: $acceptLanguage);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Language', $expected);
    }

    public function testResponsesVaryOnAcceptLanguage(): void
    {
        $client = self::createClient();

        $this->request($client, 'GET', '/api/series');

        // Ajouté par le LocaleListener de Symfony (set_locale_from_accept_language), en plus de `Vary: Accept`.
        self::assertContains('Accept-Language', $client->getResponse()->getVary());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function violationMessages(): iterable
    {
        yield 'message applicatif en français' => ['fr', 'slug', 'Le slug ne contient que des minuscules, des chiffres et des tirets.'];
        yield 'message applicatif en anglais' => ['en', 'slug', 'The slug may only contain lowercase letters, digits and dashes.'];
        yield 'message Symfony en français' => ['fr', 'year', 'Cette valeur doit être comprise entre 1900 et 2100.'];
        yield 'message Symfony en anglais' => ['en', 'year', 'This value should be between 1900 and 2100.'];
    }

    #[DataProvider('violationMessages')]
    public function testValidationMessagesAreTranslated(string $language, string $path, string $message): void
    {
        $client = self::createClient();

        $data = $this->request($client, 'POST', '/api/series', ['title' => 'X', 'slug' => 'Pas Un Slug', 'year' => 1800], as: 'admin', language: $language);

        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($data['violations']);
        $messages = array_column(array_filter($data['violations'], static fn (mixed $violation): bool => \is_array($violation) && $path === $violation['propertyPath']), 'message');
        self::assertSame([$message], $messages);
    }

    public function testDuplicateChapterMessageIsTranslatedWithItsParameters(): void
    {
        $client = self::createClient();
        $series = SeriesFactory::createOne();
        ChapterFactory::createOne(['series' => $series, 'number' => '3', 'language' => 'en']);

        $data = $this->request($client, 'POST', '/api/chapters', ['series' => '/api/series/'.$series->getId(), 'number' => '3', 'language' => 'en'], as: 'admin', language: 'en');

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Chapter 3 already exists in "en" for this series.', self::firstOf($data, 'violations')['message']);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function languageNames(): iterable
    {
        yield 'français en français' => ['fr', 'fr', 'français'];
        yield 'français en anglais' => ['fr', 'en', 'French'];
        yield 'portugais du Brésil en français' => ['pt-BR', 'fr', 'portugais (Brésil)'];
        yield 'japonais en anglais' => ['ja', 'en', 'Japanese'];
    }

    #[DataProvider('languageNames')]
    public function testChapterLanguageNameIsLocalizedWithIntl(string $chapterLanguage, string $acceptLanguage, string $expected): void
    {
        $client = self::createClient();
        $chapter = ChapterFactory::createOne(['language' => $chapterLanguage]);

        $data = $this->request($client, 'GET', '/api/chapters/'.$chapter->getId(), language: $acceptLanguage);

        self::assertSame($expected, $data['languageName']);
    }

    public function testUnknownLanguageWithAValidFormatIsRejected(): void
    {
        $client = self::createClient();

        $data = $this->request($client, 'POST', '/api/chapters', ['series' => '/api/series/'.SeriesFactory::createOne()->getId(), 'number' => '1', 'language' => 'xx'], as: 'admin');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['language', 'La langue "xx" n\'existe pas.'], [self::firstOf($data, 'violations')['propertyPath'], self::firstOf($data, 'violations')['message']]);
    }
}
