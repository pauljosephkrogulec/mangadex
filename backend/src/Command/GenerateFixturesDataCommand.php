<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:generate-fixtures-data',
    description: 'Generate fixture JSON data for 50 manhwa with chapters, covers, tags, and creators',
)]
class GenerateFixturesDataCommand extends Command
{
    private SymfonyStyle $io;
    private string $jsonDir;
    private string $coversDir;
    private string $chaptersDir;
    private string $projectDir;

    private array $tags = [];
    private array $creators = [];
    private array $scanlationGroups = [];
    private array $mangas = [];
    private array $coverArts = [];
    private array $chapters = [];
    private array $users = [];
    private array $customLists = [];
    private array $mangaFollows = [];

    protected function configure(): void
    {
        $this
            ->addOption('skip-images', null, InputOption::VALUE_NONE, 'Skip generating placeholder images')
            ->addOption('manga-count', null, InputOption::VALUE_REQUIRED, 'Number of manga to generate', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->projectDir = dirname(__DIR__, 2);
        $this->jsonDir = $this->projectDir.'/src/DataFixtures/json';
        $this->coversDir = $this->projectDir.'/public/uploads/covers';
        $this->chaptersDir = $this->projectDir.'/public/uploads/chapters';

        $count = (int) $input->getOption('manga-count');
        $skipImages = (bool) $input->getOption('skip-images');

        $this->io->title(sprintf('Generating %d manhwa with fixtures data', $count));

        $this->generateTags();
        $this->generateCreators();
        $this->generateScanlationGroups();
        $this->generateManga($count);
        $this->generateUsers();
        $this->generateCustomLists();
        $this->generateMangaFollows();

        $this->writeJsonFiles();

        if (!$skipImages) {
            $this->generatePlaceholderImages();
            $this->downloadApiCoverImages();
        }

        $mangaCount = count($this->mangas);
        $chapterCount = count($this->chapters);
        $coverCount = count($this->coverArts);

        $this->io->success(sprintf(
            'Generated %d manga, %d chapters, %d covers, %d tags, %d creators, %d scanlation groups, %d users, %d custom lists, %d manga follows',
            $mangaCount, $chapterCount, $coverCount,
            count($this->tags), count($this->creators), count($this->scanlationGroups),
            count($this->users), count($this->customLists), count($this->mangaFollows),
        ));

        return Command::SUCCESS;
    }

    private function generateTags(): void
    {
        $tagData = [
            ['action', 'Action', 'genre'],
            ['adventure', 'Adventure', 'genre'],
            ['comedy', 'Comedy', 'genre'],
            ['drama', 'Drama', 'genre'],
            ['fantasy', 'Fantasy', 'genre'],
            ['romance', 'Romance', 'genre'],
            ['sci-fi', 'Sci-Fi', 'genre'],
            ['slice-of-life', 'Slice of Life', 'genre'],
            ['thriller', 'Thriller', 'genre'],
            ['horror', 'Horror', 'genre'],
            ['martial-arts', 'Martial Arts', 'genre'],
            ['mystery', 'Mystery', 'genre'],
            ['supernatural', 'Supernatural', 'theme'],
            ['magic', 'Magic', 'theme'],
            ['monsters', 'Monsters', 'theme'],
            ['isekai', 'Isekai', 'theme'],
            ['regression', 'Regression', 'theme'],
            ['system', 'System', 'theme'],
            ['dungeons', 'Dungeons', 'theme'],
            ['academy', 'Academy', 'theme'],
            ['full-color', 'Full Color', 'format'],
            ['long-strip', 'Long Strip', 'format'],
            ['web-comic', 'Web Comic', 'format'],
            ['adaptation', 'Adaptation', 'format'],
            ['award-winning', 'Award Winning', 'format'],
        ];

        foreach ($tagData as [$ref, $name, $group]) {
            $this->tags[$ref] = [
                'ref' => $ref,
                'name' => $name,
                'groupName' => $group,
                'description' => null,
                'isPrimary' => true,
            ];
        }
    }

    private function generateCreators(): void
    {
        $names = [
            ['author' => 'Kim Yong-Hyun', 'artist' => 'Park Sung-Woo'],
            ['author' => 'Lee Jae-Hoon', 'artist' => 'Choi Min-Seo'],
            ['author' => 'Jang Hyun-Woo', 'artist' => 'Yoon Seo-Yeon'],
            ['author' => 'Park Ji-Eun', 'artist' => 'Kang Dong-Ho'],
            ['author' => 'Oh Seung-Min', 'artist' => 'Bae Jin-Young'],
            ['author' => 'Yoo Jin-Ho', 'artist' => 'Jeong Ha-Na'],
            ['author' => 'Shin Woo-Jin', 'artist' => 'Maeng Ki-Soo'],
            ['author' => 'Ahn Hyo-Seok', 'artist' => 'Go Bong-Hwa'],
            ['author' => 'Bae Yong-Joon', 'artist' => 'Seol Hyun-Ju'],
            ['author' => 'Kwak Dong-Soo', 'artist' => 'Im Ji-Yeon'],
            ['author' => 'Son Ki-Woon', 'artist' => 'Ryu Ho-Jin'],
            ['author' => 'Cha Sang-Min', 'artist' => 'Do Eun-Hye'],
            ['author' => 'Jung Pil-Won', 'artist' => 'Byun Young-Joon'],
            ['author' => 'Woo Geun-Tak', 'artist' => 'Gong Min-Ji'],
            ['author' => 'Hong Jin-Woo', 'artist' => 'Na Yeon-Soo'],
            ['author' => 'Hwang Do-Hoon', 'artist' => 'Sung Ki-Won'],
            ['author' => 'Seo Dong-Chul', 'artist' => 'Joo Hyun-Ah'],
            ['author' => 'Ko Jae-Won', 'artist' => 'Ha Jin-Sung'],
            ['author' => 'Yoon Tae-Ho', 'artist' => 'Heo Jin-Wook'],
            ['author' => 'Jang Jae-Hyuk', 'artist' => 'Min Soo-Ah'],
        ];

        foreach ($names as $pair) {
            $authorSlug = $this->slugify($pair['author']).'-author';
            $artistSlug = $this->slugify($pair['artist']).'-artist';

            $this->creators[$authorSlug] = [
                'ref' => $authorSlug,
                'name' => $pair['author'],
                'type' => 'author',
            ];

            $this->creators[$artistSlug] = [
                'ref' => $artistSlug,
                'name' => $pair['artist'],
                'type' => 'artist',
            ];
        }
    }

    private function generateScanlationGroups(): void
    {
        $groups = [
            ['mangaplus', 'MangaPlus', 'https://mangaplus.shueisha.co.jp'],
            ['viz-media', 'VIZ Media', 'https://www.viz.com'],
            ['crunchyroll', 'Crunchyroll', 'https://www.crunchyroll.com'],
            ['tapas', 'Tapas', 'https://tapas.io'],
            ['webtoon-eng', 'Webtoon English', 'https://www.webtoons.com'],
            ['flame-scan', 'Flame Scans', null],
            ['luminous-scan', 'Luminous Scans', null],
            ['asura-scan', 'Asura Scans', null],
            ['reaper-scan', 'Reaper Scans', null],
            ['leviatan-scan', 'Leviatan Scans', null],
        ];

        foreach ($groups as [$ref, $name, $website]) {
            $this->scanlationGroups[$ref] = [
                'ref' => $ref,
                'name' => $name,
                'website' => $website,
            ];
        }
    }

    private function fetchRealMangaDataFromApi(int $count): array
    {
        $url = sprintf(
            'https://api.mangadex.org/manga?limit=%d&includes[]=cover_art&order[followedCount]=desc&contentRating[]=safe&contentRating[]=suggestive&availableTranslatedLanguage[]=en',
            $count
        );

        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: MangaDexReader/1.0\r\n",
                'timeout' => 15,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        $list = [];
        foreach ($data['data'] as $manga) {
            $title = $manga['attributes']['title']['en'] ?? null;
            if (null === $title) {
                $first = reset($manga['attributes']['title']);
                $title = $first ?: 'Unknown Manga';
            }

            $coverFileName = null;
            foreach ($manga['relationships'] as $rel) {
                if ('cover_art' === $rel['type'] && isset($rel['attributes']['fileName'])) {
                    $coverFileName = $rel['attributes']['fileName'];
                    break;
                }
            }

            $list[] = [
                'id' => $manga['id'],
                'title' => $title,
                'coverFileName' => $coverFileName,
            ];
        }

        return $list;
    }

    private function fetchChaptersFromApi(string $mangaId, int $limit): array
    {
        $url = sprintf(
            'https://api.mangadex.org/manga/%s/feed?limit=%d&order[chapter]=asc&includes[]=scanlation_group',
            $mangaId,
            $limit
        );

        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: MangaDexReader/1.0\r\n",
                'timeout' => 15,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        $chapters = [];
        foreach ($data['data'] as $ch) {
            $attrs = $ch['attributes'];

            if (!empty($attrs['externalUrl'])) {
                continue;
            }

            if (($attrs['pages'] ?? 0) < 1) {
                continue;
            }

            $chapters[] = [
                'id' => $ch['id'],
                'chapterNumber' => $attrs['chapter'] ?? '1',
                'title' => $attrs['title'] ?? null,
                'language' => $attrs['translatedLanguage'] ?? 'en',
                'volume' => $attrs['volume'] ?? null,
            ];
        }

        return $chapters;
    }

    private function fetchChapterPagesFromApi(string $chapterId): ?array
    {
        $url = "https://api.mangadex.org/at-home/server/{$chapterId}";

        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: MangaDexReader/1.0\r\n",
                'timeout' => 15,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if (false === $response) {
            return null;
        }

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['chapter'])) {
            return null;
        }

        return [
            'hash' => $data['chapter']['hash'],
            'data' => $data['chapter']['data'],
            'baseUrl' => $data['baseUrl'] ?? 'https://uploads.mangadex.org',
        ];
    }

    private function generateManga(int $count): void
    {
        $titles = [
            'The Greatest Warrior Reborn',
            'Sword King of the Nether',
            'Solo Player\'s Ascent',
            'The Archmage Returns',
            'Heavenly Demon Reborn',
            'The Max Level Hero',
            'Second Life Ranker',
            'The King of Dark Dungeons',
            'Infinite Level Up',
            'God of Black Field',
            'The Guild\'s Strongest Mage',
            'Legend of the Northern Blade',
            'The Constellation That Returned',
            'Tower of Ascension',
            'The Unbeatable Swordsman',
            'Reborn as a Monster Lord',
            'The Demon King\'s Awakening',
            'Limit Breaker',
            'The Legendary Mechanic',
            'Sage of the Endless World',
            'The Martial God\'s Return',
            'Heavenly Grandmaster\'s Legacy',
            'The Cursed King',
            'Immortal Blade Master',
            'The Shadow Monarch Reborn',
            'Overgeared Blacksmith',
            'The Dragon King\'s Return',
            'Arcane Sniper',
            'The God of War Online',
            'Rebirth of the Heavenly Clan',
            'The Fifth Ranker',
            'Demon Lord\'s Reincarnation',
            'The Invincible Mercenary',
            'Sword Saint of the Ruins',
            'The Final Boss is Me',
            'Genius of the Ancient Arts',
            'The Ruler of the Land',
            'Heaven\'s Soul Hunter',
            'The Unstoppable Player',
            'Master of the Divine Arts',
            'The Return of the War God',
            'Emperor of the Abyss',
            'The Sole Survivor',
            'Celestial Executioner',
            'The Calamity Reincarnator',
            'Rise of the Phoenix King',
            'The Dark Sorcerer\'s Legacy',
            'Path of the Divine Warrior',
            'The Eternal Monarch',
            'The Beginning After the Endless',
        ];

        $realMangaList = [];
        try {
            $realMangaList = $this->fetchRealMangaDataFromApi($count);
            if ([] !== $realMangaList) {
                $this->io->writeln(sprintf('  Fetched <info>%d</info> real manga from MangaDex API', count($realMangaList)));
            }
        } catch (\Throwable $e) {
            $this->io->warning('Could not fetch real manga data: '.$e->getMessage().'. Using synthetic data.');
        }

        $useRealData = [] !== $realMangaList;

        $statuses = ['ongoing', 'completed', 'hiatus', 'cancelled'];
        $ratings = ['safe', 'suggestive'];
        $demographics = ['shounen', 'shoujo', 'josei', 'seinen', 'none'];
        $tagKeys = array_keys($this->tags);
        $creatorKeys = array_keys($this->creators);
        $groupKeys = array_keys($this->scanlationGroups);

        $descriptionSentences = [
            'In a world where dungeons and monsters threaten humanity, one hero rises above all.',
            'After being betrayed by his comrades, he returns from the brink of death with newfound powers.',
            'Follow the journey of an ordinary gamer who becomes the strongest player in a fantasy world.',
            'When the gates between worlds opened, he was the only one who could save humanity.',
            'Reincarnated into a world of magic and monsters, he must find his way back to strength.',
            'The strongest warrior of the past awakens in a new era to face even greater threats.',
            'A tale of revenge, power, and the relentless pursuit of becoming the strongest.',
            'Summoned to another world, he uses his modern knowledge to become an unstoppable force.',
        ];

        $altPrefixes = ['The ', 'A ', 'The Legend of ', 'Return of the ', 'Rebirth of the '];

        for ($i = 0; $i < $count; ++$i) {
            $realChapters = [];

            if ($useRealData && $i < count($realMangaList)) {
                $title = $realMangaList[$i]['title'];
                $mangaId = $realMangaList[$i]['id'];
                $coverFileName = $realMangaList[$i]['coverFileName'];

                try {
                    $feedChapters = $this->fetchChaptersFromApi($mangaId, 20);
                    foreach ($feedChapters as $feedCh) {
                        $pagesData = $this->fetchChapterPagesFromApi($feedCh['id']);
                        if (null === $pagesData) {
                            continue;
                        }
                        $cdnUrls = [];
                        foreach ($pagesData['data'] as $pageFile) {
                            $cdnUrls[] = \sprintf('%s/data/%s/%s', $pagesData['baseUrl'], $pagesData['hash'], $pageFile);
                        }
                        $realChapters[] = [
                            'id' => $feedCh['id'],
                            'chapterNumber' => $feedCh['chapterNumber'],
                            'title' => $feedCh['title'],
                            'language' => $feedCh['language'],
                            'volume' => $feedCh['volume'],
                            'pages' => $cdnUrls,
                        ];
                    }
                } catch (\Throwable $e) {
                    // Fall through to synthetic chapters
                }
            } elseif ($i < count($titles)) {
                $title = $titles[$i];
                $mangaId = Uuid::v4()->toRfc4122();
                $coverFileName = null;
            } else {
                break;
            }

            $slug = $this->slugify($title);
            $year = random_int(2016, 2025);

            $selectedTags = [];
            $tagCount = random_int(3, 7);
            $randomTagKeys = $tagKeys;
            shuffle($randomTagKeys);
            for ($j = 0; $j < $tagCount && $j < count($randomTagKeys); ++$j) {
                $selectedTags[] = $randomTagKeys[$j];
            }
            $selectedTags = array_unique($selectedTags);

            $selectedCreators = [];
            $authorKeys = array_values(array_filter($creatorKeys, fn ($k) => str_ends_with($k, '-author')));
            $artistKeys = array_values(array_filter($creatorKeys, fn ($k) => str_ends_with($k, '-artist')));
            shuffle($authorKeys);
            shuffle($artistKeys);

            if ([] !== $authorKeys) {
                $selectedCreators[] = $authorKeys[0];
            }
            if ([] !== $artistKeys) {
                $selectedCreators[] = $artistKeys[0];
            }

            $altTitles = [];
            $altTitlesCount = random_int(1, 4);
            for ($j = 0; $j < $altTitlesCount; ++$j) {
                $altTitles[] = $altPrefixes[array_rand($altPrefixes)].$titles[(string) random_int(0, count($titles) - 1)];
            }
            $altTitles[] = 'Tome of '.$slug;

            $this->mangas[$slug] = [
                'ref' => $slug,
                'title' => $title,
                'altTitles' => $altTitles,
                'description' => $descriptionSentences[array_rand($descriptionSentences)],
                'status' => $statuses[array_rand($statuses)],
                'year' => $year,
                'contentRating' => $ratings[array_rand($ratings)],
                'demographic' => $demographics[array_rand($demographics)],
                'creators' => $selectedCreators,
                'tags' => array_values($selectedTags),
                'id' => $mangaId,
            ];

            $this->generateCoverArts($slug, $mangaId, $coverFileName);
            $this->generateChapters($slug, $mangaId, $groupKeys, $realChapters);
        }
    }

    private function generateCoverArts(string $slug, string $mangaId, ?string $coverFileName = null): void
    {
        $volumes = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', null];

        if (null !== $coverFileName) {
            $this->coverArts[] = [
                'ref' => $slug.'-cover-primary',
                'manga' => $slug,
                'imagePath' => '/covers/'.$mangaId.'/'.$coverFileName,
                'volume' => null,
                'isPrimary' => true,
                'fromApi' => true,
            ];

            return;
        }

        $coverCount = random_int(1, 3);
        for ($i = 0; $i < $coverCount; ++$i) {
            $coverId = Uuid::v4()->toRfc4122();
            $volume = $volumes[array_rand($volumes)];
            $ext = 0 === random_int(0, 1) ? 'jpg' : 'png';

            $this->coverArts[] = [
                'ref' => $slug.'-cover-'.($volume ?? 'default').'-'.$i,
                'manga' => $slug,
                'imagePath' => '/covers/'.$mangaId.'/'.$coverId.'.'.$ext,
                'volume' => $volume,
                'isPrimary' => 0 === $i,
            ];
        }
    }

    private function generateChapters(string $slug, string $mangaId, array $groupKeys, array $realChapters = []): void
    {
        if ([] !== $realChapters) {
            foreach ($realChapters as $idx => $ch) {
                $chapterNum = $ch['chapterNumber'];
                $this->chapters[] = [
                    'ref' => $slug.'-ch-'.str_replace('.', '-', (string) $chapterNum).'-'.$idx,
                    'manga' => $slug,
                    'volume' => $ch['volume'],
                    'chapterNumber' => (string) $chapterNum,
                    'title' => $ch['title'],
                    'language' => $ch['language'],
                    'pages' => $ch['pages'],
                    'scanlationGroup' => $groupKeys[array_rand($groupKeys)],
                    'id' => $ch['id'],
                ];
            }

            return;
        }

        $chapterCount = random_int(5, 20);
        $languages = ['en', 'ja', 'ko', 'zh'];

        for ($i = 0; $i < $chapterCount; ++$i) {
            $chapterNum = $i + 1;
            $volume = (string) (intdiv($chapterNum, 10) + 1);
            $pageCount = random_int(4, 12);
            $chapterId = Uuid::v4()->toRfc4122();
            $hash = str_replace('-', '', $chapterId);

            $pages = [];
            for ($p = 0; $p < $pageCount; ++$p) {
                $pages[] = \sprintf('https://uploads.mangadex.org/data/%s/%d-%s.jpg', $hash, $p + 1, \bin2hex(\random_bytes(32)));
            }

            $chapterTitles = [
                'Prologue', 'The Beginning', 'Awakening', 'First Steps',
                'Encounter', 'The Challenge', 'New Power', 'Allies and Enemies',
                'The Turning Point', 'Revelation', 'Confrontation', 'Aftermath',
                'Training Arc', 'The Tournament', 'Final Battle', 'A New Dawn',
                null, null, null, null,
            ];

            $this->chapters[] = [
                'ref' => $slug.'-ch-'.$chapterNum,
                'manga' => $slug,
                'volume' => $volume,
                'chapterNumber' => (string) $chapterNum,
                'title' => $chapterTitles[array_rand($chapterTitles)],
                'language' => $languages[array_rand($languages)],
                'pages' => $pages,
                'scanlationGroup' => $groupKeys[array_rand($groupKeys)],
                'id' => $chapterId,
            ];
        }
    }

    private function generateUsers(): void
    {
        $userData = [
            ['ref' => 'admin', 'email' => 'admin@example.com', 'username' => 'admin', 'password' => 'admin123', 'roles' => ['ROLE_ADMIN']],
            ['ref' => 'mangareader1', 'email' => 'user1@example.com', 'username' => 'mangareader1', 'password' => 'password123', 'roles' => ['ROLE_USER']],
            ['ref' => 'mangareader2', 'email' => 'user2@example.com', 'username' => 'mangareader2', 'password' => 'password123', 'roles' => ['ROLE_USER']],
            ['ref' => 'animefan', 'email' => 'fan@example.com', 'username' => 'animefan', 'password' => 'password123', 'roles' => ['ROLE_USER']],
            ['ref' => 'scanlator', 'email' => 'scan@example.com', 'username' => 'scanlator', 'password' => 'password123', 'roles' => ['ROLE_USER']],
        ];

        foreach ($userData as $u) {
            $this->users[$u['ref']] = $u;
        }
    }

    private function generateCustomLists(): void
    {
        $mangaKeys = array_keys($this->mangas);
        $userKeys = array_keys($this->users);

        if ([] === $mangaKeys || [] === $userKeys) {
            return;
        }

        $listNames = [
            'favorites-1' => ['name' => 'My Favorite Manga', 'visibility' => 'public', 'count' => 3],
            'action-1' => ['name' => 'Action Packed', 'visibility' => 'public', 'count' => 3],
            'read-later-1' => ['name' => 'Read Later', 'visibility' => 'private', 'count' => 2],
            'top-picks-2' => ['name' => 'Top Picks', 'visibility' => 'public', 'count' => 2],
            'romance-2' => ['name' => 'Romance Reads', 'visibility' => 'public', 'count' => 3],
            'hidden-2' => ['name' => 'Hidden Gems', 'visibility' => 'hidden', 'count' => 2],
            'admin-picks' => ['name' => 'Admin Picks', 'visibility' => 'public', 'count' => 4],
        ];

        $userIndex = 0;
        foreach ($listNames as $ref => $listInfo) {
            $userRef = $userKeys[$userIndex % count($userKeys)];
            if (str_ends_with($ref, '-1')) {
                $userIndex = 1;
            } elseif (str_ends_with($ref, '-2')) {
                $userIndex = 2;
            } else {
                $userIndex = 0;
            }
            $userRef = $userKeys[$userIndex];

            $selected = [];
            $pool = $mangaKeys;
            shuffle($pool);
            for ($i = 0; $i < $listInfo['count'] && $i < count($pool); ++$i) {
                $selected[] = $pool[$i];
            }

            $this->customLists[$ref] = [
                'ref' => $ref,
                'name' => $listInfo['name'],
                'visibility' => $listInfo['visibility'],
                'user' => $userRef,
                'mangas' => $selected,
            ];

            ++$userIndex;
        }
    }

    private function generateMangaFollows(): void
    {
        $mangaKeys = array_keys($this->mangas);
        $userKeys = array_keys($this->users);

        if ([] === $mangaKeys || [] === $userKeys) {
            return;
        }

        $followId = 0;
        foreach ($userKeys as $userRef) {
            $followCount = random_int(2, 4);
            $pool = $mangaKeys;
            shuffle($pool);
            for ($i = 0; $i < $followCount && $i < count($pool); ++$i) {
                ++$followId;
                $this->mangaFollows['follow-'.$followId] = [
                    'ref' => 'follow-'.$followId,
                    'user' => $userRef,
                    'manga' => $pool[$i],
                ];
            }
        }
    }

    private function generatePlaceholderImages(): void
    {
        $this->io->section('Generating placeholder images...');

        $coverCount = 0;

        foreach ($this->coverArts as $cover) {
            if (!empty($cover['fromApi'])) {
                continue;
            }
            preg_match('#/covers/([^/]+)/(.+)#', $cover['imagePath'], $m);
            if (count($m) < 3) {
                continue;
            }
            $mangaId = $m[1];
            $fileName = $m[2];
            $dir = $this->coversDir.'/'.$mangaId;

            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }

            $path = $dir.'/'.$fileName;
            if (!file_exists($path)) {
                $this->createPlaceholderImage($path, 350, 500);
                ++$coverCount;
            }
        }

        $this->io->writeln(sprintf('  Generated %d cover images', $coverCount));
    }

    private function downloadApiCoverImages(): void
    {
        $apiCovers = array_filter($this->coverArts, fn ($c) => !empty($c['fromApi']));

        if ([] === $apiCovers) {
            return;
        }

        $this->io->section('Downloading real cover images from MangaDex...');

        $curlCommands = [];
        $createdDirs = [];
        $totalExpected = 0;

        foreach ($apiCovers as $cover) {
            preg_match('#^/covers/([^/]+)/(.+)$#', $cover['imagePath'], $m);

            if (count($m) < 3) {
                continue;
            }

            $mangaId = $m[1];
            $fileName = $m[2];
            $dir = $this->coversDir.'/'.$mangaId;
            $localPath = $dir.'/'.$fileName;

            if (file_exists($localPath)) {
                continue;
            }

            if (!in_array($dir, $createdDirs, true)) {
                if (!is_dir($dir)) {
                    mkdir($dir, 0o755, true);
                }
                $createdDirs[] = $dir;
            }

            $url = escapeshellarg("https://mangadex.org/covers/{$mangaId}/{$fileName}.512.jpg");
            $out = escapeshellarg($localPath);
            $curlCommands[] = "curl -sf -o {$out} {$url}";
            ++$totalExpected;
        }

        if (0 === $totalExpected) {
            $this->io->writeln('  All cover images already downloaded');

            return;
        }

        $batchFile = $this->projectDir.'/var/curl_covers.txt';
        $logFile = $this->projectDir.'/var/curl_covers.log';

        if (!is_dir($this->projectDir.'/var')) {
            mkdir($this->projectDir.'/var', 0o755, true);
        }

        $this->io->writeln(sprintf('  Queueing <info>%d</info> cover downloads (parallel)...', $totalExpected));
        file_put_contents($batchFile, implode("\n", $curlCommands));

        $cmd = sprintf('cat %s | xargs -P 3 -I {} sh -c "{}" 2>%s', escapeshellarg($batchFile), escapeshellarg($logFile));
        exec($cmd);

        $downloaded = 0;
        foreach ($apiCovers as $cover) {
            preg_match('#^/covers/([^/]+)/(.+)$#', $cover['imagePath'], $m);
            if (count($m) < 3) {
                continue;
            }
            $localPath = $this->coversDir.'/'.$m[1].'/'.$m[2];
            if (file_exists($localPath)) {
                ++$downloaded;
            }
        }

        $this->io->writeln(sprintf('  Downloaded <info>%d</info> / %d real cover images', $downloaded, $totalExpected));
    }

    private function createPlaceholderImage(string $path, int $width, int $height): void
    {
        $img = \imagecreatetruecolor($width, $height);

        $bgR = random_int(30, 70);
        $bgG = random_int(30, 70);
        $bgB = random_int(40, 80);
        $bg = \imagecolorallocate($img, $bgR, $bgG, $bgB);
        \imagefill($img, 0, 0, $bg);

        $accentR = random_int(100, 255);
        $accentG = random_int(100, 255);
        $accentB = random_int(100, 255);
        $accent = \imagecolorallocate($img, $accentR, $accentG, $accentB);

        for ($i = 0; $i < 5; ++$i) {
            $x1 = random_int(0, $width);
            $y1 = random_int(0, $height);
            $x2 = random_int(0, $width);
            $y2 = random_int(0, $height);
            \imageline($img, $x1, $y1, $x2, $y2, $accent);
        }

        for ($i = 0; $i < 3; ++$i) {
            $cx = random_int(0, $width);
            $cy = random_int(0, $height);
            $r = random_int(20, 100);
            \imagearc($img, $cx, $cy, $r, $r, 0, 360, $accent);
        }

        $textColor = \imagecolorallocate($img, 200, 200, 200);
        $text = basename($path);
        if (\function_exists('imagettftext')) {
            $fontSize = $width > 300 ? 12 : 8;
            @\imagettftext($img, $fontSize, 0, 10, $height - 15, $textColor, '', $text);
        }

        \imagejpeg($img, $path, 75);
        \imagedestroy($img);
    }

    private function writeJsonFiles(): void
    {
        if (!is_dir($this->jsonDir)) {
            mkdir($this->jsonDir, 0o755, true);
        }

        $files = [
            'tags.json' => $this->tags,
            'creators.json' => $this->creators,
            'scanlation_groups.json' => $this->scanlationGroups,
            'mangas.json' => $this->mangas,
            'cover_arts.json' => $this->coverArts,
            'chapters.json' => $this->chapters,
            'users.json' => $this->users,
            'custom_lists.json' => $this->customLists,
            'manga_follows.json' => $this->mangaFollows,
        ];

        foreach ($files as $filename => $data) {
            $path = $this->jsonDir.'/'.$filename;
            $json = json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            file_put_contents($path, $json."\n");
            $this->io->writeln(sprintf('  Wrote <info>%s</info> (%d entries)', $filename, count($data)));
        }
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        $text = preg_replace('/-+/', '-', $text);
        $text = mb_substr($text, 0, 100);

        return '' === $text ? 'unknown' : $text;
    }
}
