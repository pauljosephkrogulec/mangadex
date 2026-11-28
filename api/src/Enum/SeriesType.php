<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Format éditorial d'une série : détermine notamment le mode de lecture (pages ou défilement vertical).
 */
enum SeriesType: string
{
    case Manga = 'manga';
    case Manhwa = 'manhwa';
    case Manhua = 'manhua';
    case Webtoon = 'webtoon';
}
