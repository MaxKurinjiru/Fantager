<?php

declare(strict_types=1);

namespace App\Message;

use App\Service\Combat\CombatWaveProcessor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CombatWaveHandler
{
    public function __construct(
        private readonly CombatWaveProcessor $processor,
    ) {
    }

    public function __invoke(CombatWave $message): void
    {
        $this->processor->process($message);
    }
}
