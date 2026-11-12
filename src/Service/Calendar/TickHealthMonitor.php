<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TickHealthMonitor
{
    private const ALERT_INTERVAL_SECONDS = 3600;
    private const STALE_SECONDS = 900;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly KingdomTickRunnerService $tickRunner,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function checkKingdom(Kingdom $kingdom): void
    {
        $kingdomId = $kingdom->getId();
        if (null === $kingdomId) {
            return;
        }

        $connection = $this->em->getConnection();
        $failed = $connection->fetchAssociative(
            'SELECT phase.id FROM kingdom_tick_log phase INNER JOIN kingdom_tick_event event ON event.id = phase.event_id '
            ."WHERE event.kingdom_id = ? AND phase.status = 'failed' ORDER BY phase.id LIMIT 1",
            [$kingdomId],
        );
        if (false !== $failed) {
            $this->alert('failed_'.$kingdomId, sprintf('Tick health: failed tick %d in kingdom %d.', $failed['id'], $kingdomId));
        }

        $stale = $connection->fetchAssociative(
            'SELECT phase.id, phase.status FROM kingdom_tick_log phase INNER JOIN kingdom_tick_event event ON event.id = phase.event_id '
            ."WHERE event.kingdom_id = ? AND phase.status IN ('dispatched', 'processing', 'awaiting_combat') AND phase.executed_at < ? "
            .'ORDER BY phase.executed_at, phase.id LIMIT 1',
            [$kingdomId, gmdate('Y-m-d H:i:s', time() - self::STALE_SECONDS)],
        );
        if (false !== $stale) {
            $this->alert('blocked_'.$kingdomId, sprintf('Tick health: tick %d in kingdom %d has been %s for over 15 minutes.', $stale['id'], $kingdomId, $stale['status']));
        }

        $recoveries = $this->tickRunner->getStatusSnapshot($kingdom)['combat_recoveries'];
        if ([] !== $recoveries) {
            $recovery = $recoveries[0];
            $this->alert('combat_'.$kingdomId, sprintf('Tick health: battle %d in kingdom %d repeatedly recovered without progress past round %d.', $recovery['battle_id'], $kingdomId, $recovery['round']));
        }
    }

    public function checkQueue(): void
    {
        $oldest = $this->em->getConnection()->fetchOne(
            'SELECT MIN(available_at) FROM messenger_messages WHERE queue_name = ? AND available_at <= ?',
            ['default', gmdate('Y-m-d H:i:s')],
        );
        if (is_string($oldest) && $oldest < gmdate('Y-m-d H:i:s', time() - self::STALE_SECONDS)) {
            $this->alert('queue', 'Tick health: the async queue contains a message available for over 15 minutes.');
        }
    }

    private function alert(string $key, string $message): void
    {
        $this->cache->get('tick_health_'.$key, function (ItemInterface $item) use ($message): bool {
            $item->expiresAfter(self::ALERT_INTERVAL_SECONDS);
            $this->logger->error($message);

            return true;
        }, 0);
    }
}
