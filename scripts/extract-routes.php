<?php

declare(strict_types=1);

/**
 * @return list<array{method: string, path: string, file: string}>
 */
function extractRoutes(string $root): array
{
    $routes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/src/Controller', FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || 'php' !== $file->getExtension()) {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        if (false === $content || !preg_match_all('/#\[Route\((.*?)\)\]/s', $content, $matches)) {
            continue;
        }

        $relative = 'src/Controller/'.substr($file->getPathname(), strlen($root.'/src/Controller/') + 0);

        foreach ($matches[1] as $args) {
            $path = null;
            if (preg_match("/path\s*:\s*'([^']+)'/", $args, $pathMatch)) {
                $path = $pathMatch[1];
            }

            $methods = ['GET'];
            if (preg_match("/methods\s*:\s*\[(.*?)\]/s", $args, $methodsMatch)) {
                preg_match_all("/'([A-Z]+)'/", $methodsMatch[1], $methodMatches);
                if ([] !== $methodMatches[1]) {
                    $methods = $methodMatches[1];
                }
            }

            if (null === $path) {
                continue;
            }

            foreach ($methods as $method) {
                $routes[] = [
                    'method' => $method,
                    'path' => $path,
                    'file' => $relative,
                ];
            }
        }
    }

    usort($routes, static fn (array $a, array $b): int => [$a['path'], $a['method']] <=> [$b['path'], $b['method']]);

    return $routes;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    foreach (extractRoutes(dirname(__DIR__)) as $route) {
        echo $route['method']."\t".$route['path']."\t".$route['file'].PHP_EOL;
    }
}