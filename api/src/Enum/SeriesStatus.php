<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statut de publication d'une série.
 */
enum SeriesStatus: string
{
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Hiatus = 'hiatus';
    case Cancelled = 'cancelled';
}
