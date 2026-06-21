<?php

declare(strict_types=1);

/**
 * @return list<string>
 */
function flattenYamlKeys(string $yamlFile): array
{
    $lines = file($yamlFile, FILE_IGNORE_NEW_LINES);
    if (false === $lines) {
        return [];
    }

    $keys = [];
    $stack = [];

    foreach ($lines as $line) {
        if ('' === trim($line) || str_starts_with(trim($line), '#')) {
            continue;
        }

        if (!preg_match('/^(\s*)([\w.-]+):\s*/', $line, $match)) {
            continue;
        }

        $indent = (int) (strlen($match[1]) / 2);
        $key = $match[2];
        $stack = array_slice($stack, 0, $indent);
        $stack[] = $key;
        $keys[] = implode('.', $stack);
    }

    return $keys;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $file = $argv[1] ?? '';
    foreach (flattenYamlKeys($file) as $key) {
        echo $key.PHP_EOL;
    }
}