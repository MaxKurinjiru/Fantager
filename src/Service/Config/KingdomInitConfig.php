<?php

declare(strict_types=1);

namespace App\Service\Config;

/**
 * Loads kingdom bootstrap defaults from config/kingdom/*.defaults.json.
 */
class KingdomInitConfig
{
    private const CONFIG_DIR = '/config/kingdom';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /** @return array<string, mixed> */
    public function kingdom(): array
    {
        return $this->load('kingdom.defaults.json');
    }

    /** @return array<string, mixed> */
    public function leagueTiers(): array
    {
        return $this->load('league_tiers.defaults.json');
    }

    /** @return array<string, mixed> */
    public function season(): array
    {
        return $this->load('season.defaults.json');
    }

    /** @return array<string, mixed> */
    public function team(): array
    {
        return $this->load('team.defaults.json');
    }

    /** @return array<string, mixed> */
    public function npcTeams(): array
    {
        return $this->load('npc_teams.defaults.json');
    }

    /** @return array<string, mixed> */
    public function headquarters(): array
    {
        return $this->load('headquarters.defaults.json');
    }

    /** @return array<string, mixed> */
    private function load(string $filename): array
    {
        $this->warmCache();

        return $this->cache[$filename] ?? throw new \RuntimeException(sprintf('Kingdom init config "%s" is missing.', $filename));
    }

    private function warmCache(): void
    {
        if (null !== $this->cache) {
            return;
        }

        $dir = $this->projectDir.self::CONFIG_DIR;
        if (!is_dir($dir)) {
            throw new \RuntimeException(sprintf('Kingdom init config directory not found: %s', $dir));
        }

        $this->cache = [];
        foreach (glob($dir.'/*.defaults.json') ?: [] as $path) {
            $basename = basename($path);
            $raw = file_get_contents($path);
            if (false === $raw) {
                throw new \RuntimeException(sprintf('Cannot read kingdom init config: %s', $path));
            }

            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            $this->cache[$basename] = $this->stripMetaKeys($decoded);
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function stripMetaKeys(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && str_starts_with($key, '_')) {
                continue;
            }
            $out[$key] = is_array($value) ? $this->stripMetaKeys($value) : $value;
        }

        return $out;
    }
}
