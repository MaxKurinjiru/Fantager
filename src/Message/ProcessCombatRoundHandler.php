<?php

declare(strict_types=1);

namespace App\Message;

use App\Entity\Combat\Battle;
use App\Enum\BattleStatus;
use App\Service\Combat\CombatWaveProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessCombatRoundHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CombatWaveProcessor $processor,
    ) {
    }

    public function __invoke(ProcessCombatRound $message): void
    {
        $battle = $this->em->find(Battle::class, $message->getBattleId());
        if (null === $battle || BattleStatus::Simulating !== $battle->getStatus()) {
            return;
        }

        $scheduledAt = $battle->getScheduledAt();
        if (null === $scheduledAt) {
            return;
        }

        $this->processor->process(new CombatWave((int) $battle->getKingdom()->getId(), $scheduledAt, $message->getRound()));
    }
}
