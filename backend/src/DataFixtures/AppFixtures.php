<?php

namespace App\DataFixtures;

use App\Entity\Manga;
use App\Entity\Chapter;
use App\Entity\Creator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\CodeCoverageIgnore;

#[CodeCoverageIgnore]
class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $authors = [];
        $artists = [];

        $creator1 = new Creator();
        $creator1->setName('Koyoharu Gotouge');
        $creator1->setType('author');
        $manager->persist($creator1);
        $authors[] = $creator1;

        $creator2 = new Creator();
        $creator2->setName('Koyoharu Gotouge');
        $creator2->setType('artist');
        $manager->persist($creator2);
        $artists[] = $creator2;

        $creator3 = new Creator();
        $creator3->setName('Eiichiro Oda');
        $creator3->setType('author');
        $manager->persist($creator3);
        $authors[] = $creator3;

        $creator4 = new Creator();
        $creator4->setName('Eiichiro Oda');
        $creator4->setType('artist');
        $manager->persist($creator4);
        $artists[] = $creator4;

        $creator5 = new Creator();
        $creator5->setName('Hirohiko Araki');
        $creator5->setType('author');
        $manager->persist($creator5);
        $authors[] = $creator5;

        $creator6 = new Creator();
        $creator6->setName('Hirohiko Araki');
        $creator6->setType('artist');
        $manager->persist($creator6);
        $artists[] = $creator6;

        $manga1 = new Manga();
        $manga1->setTitle('Demon Slayer: Kimetsu no Yaiba');
        $manga1->setAltTitles(['鬼滅の刃', 'Kimetsu no Yaiba']);
        $manga1->setDescription('Tanjiro Kamado sets out to become a demon slayer to avenge his family and cure his sister.');
        $manga1->setStatus('completed');
        $manga1->setYear(2016);
        $manga1->setContentRating('suggestive');
        $manga1->addCreator($creator1);
        $manga1->addCreator($creator2);
        $manager->persist($manga1);

        $manga2 = new Manga();
        $manga2->setTitle('One Piece');
        $manga2->setAltTitles(['ワンピース', 'Wan Pīsu']);
        $manga2->setDescription('Gol D. Roger was known as the Pirate King, the strongest and most infamous being to have sailed the Grand Line.');
        $manga2->setStatus('ongoing');
        $manga2->setYear(1997);
        $manga2->setContentRating('suggestive');
        $manga2->addCreator($creator3);
        $manga2->addCreator($creator4);
        $manager->persist($manga2);

        $manga3 = new Manga();
        $manga3->setTitle('JoJo\'s Bizarre Adventure: Part 3 - Stardust Crusaders');
        $manga3->setAltTitles(['ジョジョの奇妙な冒険', 'JoJo no Kimyou na Bouken']);
        $manga3->setDescription('Jotaro Kujo and his companions travel to Egypt to defeat DIO.');
        $manga3->setStatus('completed');
        $manga3->setYear(1989);
        $manga3->setContentRating('suggestive');
        $manga3->addCreator($creator5);
        $manga3->addCreator($creator6);
        $manager->persist($manga3);

        $chapter1 = new Chapter();
        $chapter1->setManga($manga1);
        $chapter1->setVolume('1');
        $chapter1->setChapterNumber('1');
        $chapter1->setTitle('Cruelty');
        $chapter1->setLanguage('en');
        $chapter1->setPages(['001.jpg', '002.jpg', '003.jpg']);
        $manager->persist($chapter1);

        $chapter2 = new Chapter();
        $chapter2->setManga($manga1);
        $chapter2->setVolume('1');
        $chapter2->setChapterNumber('2');
        $chapter2->setTitle('A Fragrant Flower');
        $chapter2->setLanguage('en');
        $chapter2->setPages(['001.jpg', '002.jpg', '003.jpg']);
        $manager->persist($chapter2);

        $chapter3 = new Chapter();
        $chapter3->setManga($manga2);
        $chapter3->setVolume('1');
        $chapter3->setChapterNumber('1');
        $chapter3->setTitle('Romance Dawn');
        $chapter3->setLanguage('en');
        $chapter3->setPages(['001.jpg', '002.jpg', '003.jpg']);
        $manager->persist($chapter3);

        $chapter4 = new Chapter();
        $chapter4->setManga($manga3);
        $chapter4->setVolume('1');
        $chapter4->setChapterNumber('1');
        $chapter4->setTitle('Jotaro Kujo');
        $chapter4->setLanguage('en');
        $chapter4->setPages(['001.jpg', '002.jpg', '003.jpg']);
        $manager->persist($chapter4);

        $manager->flush();
    }
}
