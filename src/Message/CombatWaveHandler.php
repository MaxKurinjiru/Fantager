<?php

declare(strict_types=1);

namespace App\Message;

use App\Entity\Combat\Battle;
use App\Enum\BattleStatus;
use App\Repository\Combat\BattleRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class CombatWaveHandler
{
    public function __construct(
        private readonly BattleRepository $battleRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(CombatWave $message): void
    {
        $kingdomId = $message->getKingdomId();
        $scheduledAt = $message->getScheduledAt();
        $round = $message->getRound();

        // Find all simulating battles in this cohort (kingdom + scheduledAt)
        $battles = $this->battleRepository->createQueryBuilder('b')
            ->where('b.kingdom = :kingdomId')
            ->andWhere('b.scheduledAt = :scheduledAt')
            ->andWhere('b.status = :status')
            ->setParameter('kingdomId', $kingdomId)
            ->setParameter('scheduledAt', $scheduledAt)
            ->setParameter('status', BattleStatus::Simulating)
            ->getQuery()
            ->getResult();

        foreach ($battles as $battle) {
            /** @var Battle $battle */
            if ($battle->getCurrentRound() === $round - 1) {
                $this->messageBus->dispatch(new ProcessCombatRound((int) $battle->getId(), $round));
            }
        }
    }
}
