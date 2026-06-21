<?php

declare(strict_types=1);

namespace App\Service\Community;

class ContentFilterService
{
    private const BLACKLIST = [
        'debil', 'blbec', 'curak', 'čurák', 'picus', 'píč', 'pica', 'píča', 'kokot', 'hovno', 'prdel', 'srac', 'sráč',
        'fuck', 'shit', 'asshole', 'bitch', 'crap', 'cunt', 'bastard', 'dick',
    ];

    /**
     * Filter profane words and replace them with asterisks.
     */
    public function filterContent(string $text): string
    {
        $filtered = $text;
        foreach (self::BLACKLIST as $word) {
            // Case-insensitive, Unicode-safe pattern replacement
            $pattern = '/'.preg_quote($word, '/').'/ui';
            $filtered = preg_replace_callback($pattern, static function (array $matches): string {
                return str_repeat('*', mb_strlen($matches[0]));
            }, $filtered) ?? $filtered;
        }

        return $filtered;
    }
}
