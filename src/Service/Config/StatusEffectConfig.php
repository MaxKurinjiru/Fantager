<?php

declare(strict_types=1);

namespace App\Service\Config;

use App\Enum\StatusEffect;
use App\ValueObject\StatusEffectDefinition;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads status effect definitions from config/game/status_effects.yaml.
 */
class StatusEffectConfig
{
    /** @var array<string, StatusEffectDefinition> */
    private array $definitions = [];

    public function __construct(string $projectDir)
    {
        /** @var array{effects: array<string, array<string, mixed>>} $parsed */
        $parsed = Yaml::parseFile($projectDir.'/config/game/status_effects.yaml');

        foreach ($parsed['effects'] as $key => $data) {
            $this->definitions[$key] = StatusEffectDefinition::fromArray($key, $data);
        }
    }

    public function get(StatusEffect $effect): StatusEffectDefinition
    {
        if (!isset($this->definitions[$effect->value])) {
            throw new \InvalidArgumentException(sprintf('Status effect "%s" is not defined.', $effect->value));
        }

        return $this->definitions[$effect->value];
    }

    /**
     * @return array<string, StatusEffectDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }
}
