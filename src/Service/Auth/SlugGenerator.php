<?php

declare(strict_types=1);

namespace App\Service\Auth;

class SlugGenerator
{
    public function generate(string $name): string
    {
        $str = $name;

        if (function_exists('transliterator_transliterate')) {
            $str = (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $str);
        } else {
            $str = strtolower($str);
        }

        $str = (string) preg_replace('/[^a-z0-9]+/', '-', $str);

        return trim($str, '-');
    }
}
