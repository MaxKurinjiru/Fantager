<?php

declare(strict_types=1);

namespace App\Service\TeamChronicle;

use App\Entity\Team\Team;
use App\Entity\Team\TeamChronicle;
use App\Enum\ChronicleEventType;
use App\Repository\Team\TeamChronicleRepository;

/**
 * Splits a team chronicle into manager and NPC stretches.
 *
 * A player period runs from `player_joined` through the matching `player_released`.
 * The stretch before the first claim, and each stretch after a release, is NPC.
 */
class ChronicleOwnershipPeriodService
{
    public function __construct(
        private readonly TeamChronicleRepository $teamChronicleRepository,
    ) {
    }

    /**
     * @return list<array{
     *     key: string,
     *     kind: string,
     *     name: string|null,
     *     user_id: int|null,
     *     from: \DateTimeImmutable,
     *     from_inclusive: bool,
     *     to: \DateTimeImmutable|null,
     *     to_inclusive: bool,
     *     current: bool
     * }>
     */
    public function listForTeam(Team $team): array
    {
        return $this->build($this->teamChronicleRepository->findOwnershipMarkers($team));
    }

    /**
     * @param list<TeamChronicle> $markers chronological ownership events
     *
     * @return list<array{
     *     key: string,
     *     kind: string,
     *     name: string|null,
     *     user_id: int|null,
     *     from: \DateTimeImmutable,
     *     from_inclusive: bool,
     *     to: \DateTimeImmutable|null,
     *     to_inclusive: bool,
     *     current: bool
     * }>
     */
    public function build(array $markers): array
    {
        /** @var list<array{key: string, kind: string, name: string|null, user_id: int|null, from: \DateTimeImmutable, from_inclusive: bool, to: \DateTimeImmutable|null, to_inclusive: bool, current: bool}> $periods */
        $periods = [];
        /** @var array{key: string, kind: string, name: string|null, user_id: int|null, from: \DateTimeImmutable, from_inclusive: bool, to: \DateTimeImmutable|null, to_inclusive: bool, current: bool}|null $open */
        $open = null;

        foreach ($markers as $marker) {
            $id = $marker->getId();
            if (null === $id) {
                continue;
            }

            $at = $marker->getCreatedAt();
            switch ($marker->getType()) {
                case ChronicleEventType::TeamEstablished:
                    if (null === $open) {
                        $open = $this->npcPeriod('e-'.$id, $at, true);
                    }
                    break;
                case ChronicleEventType::PlayerJoined:
                    $open = $this->close($open, $periods, $at, false);
                    $userId = $marker->getData()['user_id'] ?? null;
                    $open = [
                        'key' => (string) $id,
                        'kind' => 'player',
                        'name' => (string) ($marker->getSubjectParams()['player'] ?? ''),
                        'user_id' => is_int($userId) ? $userId : null,
                        'from' => $at,
                        'from_inclusive' => true,
                        'to' => null,
                        'to_inclusive' => false,
                        'current' => true,
                    ];
                    break;
                case ChronicleEventType::PlayerReleased:
                    $open = $this->close($open, $periods, $at, true);
                    $open = $this->npcPeriod('n-'.$id, $at, false);
                    break;
                default:
                    break;
            }
        }

        if (null !== $open) {
            $open['current'] = true;
            $periods[] = $open;
        }

        return $periods;
    }

    /**
     * @param list<array{key: string, kind: string, name: string|null, user_id: int|null, from: \DateTimeImmutable, from_inclusive: bool, to: \DateTimeImmutable|null, to_inclusive: bool, current: bool}> $periods
     */
    public function windowFor(array $periods, string $key): ?ChroniclePeriodWindow
    {
        if ('' === $key) {
            return null;
        }

        foreach ($periods as $period) {
            if ($period['key'] !== $key) {
                continue;
            }

            return new ChroniclePeriodWindow(
                $period['from'],
                $period['from_inclusive'],
                $period['to'],
                $period['to_inclusive'],
            );
        }

        return null;
    }

    /**
     * @param array{key: string, kind: string, name: string|null, user_id: int|null, from: \DateTimeImmutable, from_inclusive: bool, to: \DateTimeImmutable|null, to_inclusive: bool, current: bool}|null $open
     * @param list<array{key: string, kind: string, name: string|null, user_id: int|null, from: \DateTimeImmutable, from_inclusive: bool, to: \DateTimeImmutable|null, to_inclusive: bool, current: bool}> $periods
     *
     * @return null
     */
    private function close(?array $open, array &$periods, \DateTimeImmutable $at, bool $inclusive): null
    {
        if (null === $open) {
            return null;
        }

        $open['to'] = $at;
        $open['to_inclusive'] = $inclusive;
        $open['current'] = false;
        $periods[] = $open;

        return null;
    }

    /**
     * @return array{
     *     key: string,
     *     kind: string,
     *     name: null,
     *     user_id: null,
     *     from: \DateTimeImmutable,
     *     from_inclusive: bool,
     *     to: null,
     *     to_inclusive: false,
     *     current: true
     * }
     */
    private function npcPeriod(string $key, \DateTimeImmutable $from, bool $inclusive): array
    {
        return [
            'key' => $key,
            'kind' => 'npc',
            'name' => null,
            'user_id' => null,
            'from' => $from,
            'from_inclusive' => $inclusive,
            'to' => null,
            'to_inclusive' => false,
            'current' => true,
        ];
    }
}
