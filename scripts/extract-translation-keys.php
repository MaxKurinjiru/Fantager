<?php

declare(strict_types=1);

/**
 * @return list<array{domain: string, key: string}>
 */
function extractTranslationKeys(string $root): array
{
    $keys = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/src', FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || 'php' !== $file->getExtension()) {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        if (false === $content) {
            continue;
        }

        if (preg_match_all("/UserFacingException\s*\(\s*'([^']+)'/", $content, $matches)) {
            foreach ($matches[1] as $key) {
                $keys["messages\t$key"] = ['domain' => 'messages', 'key' => $key];
            }
        }

        if (preg_match_all("/jsonError\s*\(\s*'([^']+)'/", $content, $matches)) {
            foreach ($matches[1] as $key) {
                $keys["messages\t$key"] = ['domain' => 'messages', 'key' => $key];
            }
        }

        if (preg_match_all("/->trans(?:Message)?\s*\(\s*'([^']+)'/", $content, $matches)) {
            foreach ($matches[1] as $key) {
                $keys["messages\t$key"] = ['domain' => 'messages', 'key' => $key];
            }
        }

        if (preg_match_all("/addFlash\s*\(\s*'[^']+'\s*,\s*(?:\\\$this->userMessages->trans\(\s*)?'([^']+)'/", $content, $matches)) {
            foreach ($matches[1] as $key) {
                $keys["messages\t$key"] = ['domain' => 'messages', 'key' => $key];
            }
        }
    }

    $result = array_values($keys);
    usort($result, static fn (array $a, array $b): int => [$a['domain'], $a['key']] <=> [$b['domain'], $b['key']]);

    return $result;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    foreach (extractTranslationKeys(dirname(__DIR__)) as $entry) {
        echo $entry['domain']."\t".$entry['key'].PHP_EOL;
    }
}