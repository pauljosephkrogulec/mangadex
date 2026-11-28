<?php

declare(strict_types=1);

namespace App\HtmlSanitizer;

/**
 * Propriété texte contenant du HTML fourni par un utilisateur ou un import : elle est nettoyée par le
 * sanitizer `app.rich_text` avant chaque écriture en base (HtmlSanitizerListener, issue #35).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class SanitizeHtml
{
}
