<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Cycle de vie d'un chapitre : brouillon, programmé (date de publication future) puis publié.
 */
enum ChapterStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
}
