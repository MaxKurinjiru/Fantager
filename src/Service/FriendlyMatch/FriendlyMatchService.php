<?php

declare(strict_types=1);

namespace App\Service\FriendlyMatch;

use App\Entity\Formation\Formation;
use App\Entity\FriendlyMatch\FriendlyMatch;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\FriendlyMatchStatus;
use App\Exception\UserFacingException;
use App\Repository\Formation\FormationRepository;
use App\Repository\FriendlyMatch\FriendlyMatchRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Business rules for PvP friendly matches outside the league.
 */
class FriendlyMatchService
{
    /** Invite cutoff: must accept/create at least 6 hours before kick-off. */
    private const INVITE_CUTOFF_SECONDS = 6 * 3600;

    /** Cancel cutoff: may cancel up to 1 hour before kick-off. */
    private const CANCEL_CUTOFF_SECONDS = 3600;

    /** Slot horizon: up to 14 days ahead. */
    private const SLOT_HORIZON_DAYS = 14;

    /** Home cap: max 1 home friendly per team per ISO week. */
    private const HOME_CAP = 1;

    public function __construct(
        private readonly FriendlyMatchRepository $friendlyMatchRepository,
        private readonly FormationRepository $formationRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * List all valid scheduledAt values (UTC, ATOM format) where a new invite can be created.
     *
     * A slot is valid when:
     * 1. It is a Wednesday or Sunday at 18:00 local kingdom time, converted to UTC
     * 2. It falls within (now, now + 14 days]
     * 3. The invite cutoff is still open (scheduledAt − 6h > now)
     * 4. Challenger has no home friendly (pending/scheduled/in_progress) in that ISO week
     * 5. Neither team already has an active friendly at that exact scheduledAt
     *
     * @return list<string> scheduled_at values in ATOM/UTC format
     *
     * @throws UserFacingException
     */
    public function listAvailableSlots(Team $challenger, Team $opponent, \DateTimeImmutable $now): array
    {
        $this->assertPvpEligible($challenger, $opponent);

        $kingdom = $challenger->getKingdom();

        try {
            $tz = new \DateTimeZone($kingdom->getTimezone());
        } catch (\Exception) {
            $tz = new \DateTimeZone('UTC');
        }

        $horizon = $now->modify(sprintf('+%d days', self::SLOT_HORIZON_DAYS));

        // Iterate day by day in kingdom TZ, collect Wed/Sun 18:00 instants
        $current = $now->setTimezone($tz)->setTime(0, 0, 0);
        $endLocal = $horizon->setTimezone($tz)->modify('+1 day');

        $activeStatuses = [
            FriendlyMatchStatus::Pending,
            FriendlyMatchStatus::Scheduled,
            FriendlyMatchStatus::InProgress,
        ];

        $slots = [];

        while ($current <= $endLocal) {
            $dayOfWeek = (int) $current->format('N'); // 1=Mon … 7=Sun

            if (3 === $dayOfWeek || 7 === $dayOfWeek) { // Wednesday=3, Sunday=7
                $localSlot = new \DateTimeImmutable(
                    $current->format('Y-m-d').' 18:00:00',
                    $tz
                );
                $utcSlot = $localSlot->setTimezone(new \DateTimeZone('UTC'));

                // Must be strictly in the future and within horizon
                if ($utcSlot <= $now || $utcSlot > $horizon) {
                    $current = $current->modify('+1 day');
                    continue;
                }

                // Invite cutoff must still be open
                if ($utcSlot->getTimestamp() - $now->getTimestamp() <= self::INVITE_CUTOFF_SECONDS) {
                    $current = $current->modify('+1 day');
                    continue;
                }

                // Home cap: challenger must not already have a home friendly in this ISO week
                [$weekStart, $weekEnd] = $this->isoWeekBounds($localSlot, $tz);
                $homeCount = $this->friendlyMatchRepository->countHomeInWeek(
                    $challenger,
                    $weekStart->setTimezone(new \DateTimeZone('UTC')),
                    $weekEnd->setTimezone(new \DateTimeZone('UTC')),
                    $activeStatuses,
                );
                if ($homeCount >= self::HOME_CAP) {
                    $current = $current->modify('+1 day');
                    continue;
                }

                // Conflict: neither team may have an active friendly at this exact time
                $conflict = $this->friendlyMatchRepository->findTeamConflictAtTime(
                    $challenger,
                    $opponent,
                    $utcSlot,
                    $activeStatuses,
                );
                if (null !== $conflict) {
                    $current = $current->modify('+1 day');
                    continue;
                }

                $slots[] = $utcSlot->format(\DateTimeInterface::ATOM);
            }

            $current = $current->modify('+1 day');
        }

        return $slots;
    }

    /**
     * Create a pending invite (challenger = home team).
     *
     * @throws UserFacingException
     */
    public function createInvite(Team $challenger, Team $opponent, \DateTimeImmutable $scheduledAt, \DateTimeImmutable $now): FriendlyMatch
    {
        $this->assertPvpEligible($challenger, $opponent);

        // Validate scheduledAt is in the available slots
        $validSlots = $this->listAvailableSlots($challenger, $opponent, $now);
        $slotUtc = $scheduledAt->setTimezone(new \DateTimeZone('UTC'));
        if (!in_array($slotUtc->format(\DateTimeInterface::ATOM), $validSlots, true)) {
            throw new UserFacingException('error.friendly_slot_invalid');
        }

        $match = new FriendlyMatch();
        $match->setKingdom($challenger->getKingdom());
        $match->setHomeTeam($challenger);
        $match->setAwayTeam($opponent);
        $match->setScheduledAt($slotUtc);
        $match->setStatus(FriendlyMatchStatus::Pending);

        $this->em->persist($match);
        $this->em->flush();

        return $match;
    }

    /**
     * Away team accepts a pending invite.
     *
     * @throws UserFacingException
     */
    public function accept(FriendlyMatch $match, Team $team, \DateTimeImmutable $now): void
    {
        if ($match->getAwayTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.friendly_not_participant');
        }

        if (FriendlyMatchStatus::Pending !== $match->getStatus()) {
            throw new UserFacingException('error.friendly_not_pending');
        }

        $this->assertInviteCutoff($match, $now);

        $match->setStatus(FriendlyMatchStatus::Scheduled);
        $match->setRespondedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->em->flush();
    }

    /**
     * Away team declines a pending invite.
     *
     * @throws UserFacingException
     */
    public function decline(FriendlyMatch $match, Team $team): void
    {
        if ($match->getAwayTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.friendly_not_participant');
        }

        if (FriendlyMatchStatus::Pending !== $match->getStatus()) {
            throw new UserFacingException('error.friendly_not_pending');
        }

        $match->setStatus(FriendlyMatchStatus::Declined);
        $match->setRespondedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->em->flush();
    }

    /**
     * Either participant cancels a pending or scheduled match (within cancel cutoff).
     *
     * @throws UserFacingException
     */
    public function cancel(FriendlyMatch $match, Team $team, \DateTimeImmutable $now): void
    {
        if (!$match->isParticipant($team)) {
            throw new UserFacingException('error.friendly_not_participant');
        }

        if (!in_array($match->getStatus(), [FriendlyMatchStatus::Pending, FriendlyMatchStatus::Scheduled], true)) {
            throw new UserFacingException('error.friendly_not_pending');
        }

        $secondsUntilKickoff = $match->getScheduledAt()->getTimestamp() - $now->getTimestamp();
        if ($secondsUntilKickoff <= self::CANCEL_CUTOFF_SECONDS) {
            throw new UserFacingException('error.friendly_cancel_cutoff');
        }

        $match->setStatus(FriendlyMatchStatus::Cancelled);
        $this->em->flush();
    }

    /**
     * Expire pending invites whose invite window has closed (scheduledAt − 6h <= now).
     *
     * @return int Number of matches expired
     */
    public function expireStalePending(\DateTimeImmutable $now): int
    {
        // A pending match expires when scheduledAt − 6h has already passed (i.e. scheduledAt <= now + 6h)
        $cutoff = $now->modify(sprintf('+%d seconds', self::INVITE_CUTOFF_SECONDS));

        $pendingMatches = $this->em->createQueryBuilder()
            ->select('fm')
            ->from(FriendlyMatch::class, 'fm')
            ->where('fm.status = :pending')
            ->andWhere('fm.scheduledAt <= :cutoff')
            ->setParameter('pending', FriendlyMatchStatus::Pending)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($pendingMatches as $match) {
            $match->setStatus(FriendlyMatchStatus::Expired);
            ++$count;
        }

        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
    }

    /**
     * List all friendly matches for a kingdom with optional status filter.
     *
     * @return list<FriendlyMatch>
     */
    public function listForKingdom(Kingdom $kingdom, ?FriendlyMatchStatus $status = null): array
    {
        return $this->friendlyMatchRepository->findForKingdom($kingdom, $status);
    }

    /**
     * Serialize a FriendlyMatch to a snake_case array for API responses.
     *
     * @return array<string, mixed>
     */
    public function serialize(FriendlyMatch $match): array
    {
        return [
            'id' => $match->getId(),
            'kingdom_id' => $match->getKingdom()->getId(),
            'home_team' => [
                'id' => $match->getHomeTeam()->getId(),
                'name' => $match->getHomeTeam()->getName(),
            ],
            'away_team' => [
                'id' => $match->getAwayTeam()->getId(),
                'name' => $match->getAwayTeam()->getName(),
            ],
            'scheduled_at' => $match->getScheduledAt()->format(\DateTimeInterface::ATOM),
            'status' => $match->getStatus()->value,
            'battle_id' => $match->getBattle()?->getId(),
            'created_at' => $match->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'responded_at' => $match->getRespondedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param list<FriendlyMatch> $matches
     *
     * @return list<array<string, mixed>>
     */
    public function serializeList(array $matches): array
    {
        return array_map($this->serialize(...), $matches);
    }

    // -------------------------------------------------------------------------
    // Formation assignment helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function getFormationState(FriendlyMatch $match, Team $team): array
    {
        $this->assertParticipant($match, $team);

        $assigned = $this->getAssignedFormation($match, $team);
        $default = $this->formationRepository->findOneBy(['team' => $team, 'isDefault' => true, 'isTemporary' => false]);

        $mode = 'default';
        if (null !== $assigned) {
            $mode = 'saved';
        }

        return [
            'friendly_match_id' => $match->getId(),
            'assignment' => [
                'mode' => $mode,
                'formation_id' => $assigned?->getId(),
            ],
            'default_formation_id' => $default?->getId(),
            'assigned_formation_id' => $assigned?->getId(),
        ];
    }

    /**
     * Assign team default (null) to this friendly match.
     *
     * @throws UserFacingException
     */
    public function assignDefault(FriendlyMatch $match, Team $team): void
    {
        $this->assertEditable($match, $team);
        $this->setAssignedFormation($match, $team, null);
        $this->em->flush();
    }

    /**
     * Assign a saved (non-temporary) formation to this friendly match.
     *
     * @throws UserFacingException
     */
    public function assignSavedFormation(FriendlyMatch $match, Team $team, int $formationId): void
    {
        $this->assertEditable($match, $team);

        $formation = $this->formationRepository->findOneBy(['id' => $formationId, 'team' => $team]);
        if (null === $formation) {
            throw new UserFacingException('error.formation_not_found');
        }
        if ($formation->isTemporary()) {
            throw new UserFacingException('error.formation_not_found');
        }

        $this->setAssignedFormation($match, $team, $formation);
        $this->em->flush();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function assertPvpEligible(Team $challenger, Team $opponent): void
    {
        if (null === $challenger->getUser()) {
            throw new UserFacingException('error.friendly_opponent_npc');
        }

        if (null === $opponent->getUser()) {
            throw new UserFacingException('error.friendly_opponent_npc');
        }

        if ($challenger->getId() === $opponent->getId()) {
            throw new UserFacingException('error.friendly_same_team');
        }

        if ($challenger->getKingdom()->getId() !== $opponent->getKingdom()->getId()) {
            throw new UserFacingException('error.friendly_different_kingdom');
        }
    }

    private function assertInviteCutoff(FriendlyMatch $match, \DateTimeImmutable $now): void
    {
        $secondsUntilKickoff = $match->getScheduledAt()->getTimestamp() - $now->getTimestamp();
        if ($secondsUntilKickoff <= self::INVITE_CUTOFF_SECONDS) {
            throw new UserFacingException('error.friendly_invite_cutoff');
        }
    }

    private function assertParticipant(FriendlyMatch $match, Team $team): void
    {
        if (!$match->isParticipant($team)) {
            throw new UserFacingException('error.friendly_not_participant');
        }
    }

    private function assertEditable(FriendlyMatch $match, Team $team): void
    {
        $this->assertParticipant($match, $team);

        if (FriendlyMatchStatus::Scheduled !== $match->getStatus()) {
            throw new UserFacingException('error.formation_only_scheduled');
        }
    }

    private function getAssignedFormation(FriendlyMatch $match, Team $team): ?Formation
    {
        if ($match->getHomeTeam()->getId() === $team->getId()) {
            return $match->getHomeFormation();
        }

        return $match->getAwayFormation();
    }

    private function setAssignedFormation(FriendlyMatch $match, Team $team, ?Formation $formation): void
    {
        if ($match->getHomeTeam()->getId() === $team->getId()) {
            $match->setHomeFormation($formation);
        } else {
            $match->setAwayFormation($formation);
        }
    }

    /**
     * Returns [Monday 00:00, Sunday 23:59:59] in the given timezone for the ISO week containing $dt.
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private function isoWeekBounds(\DateTimeImmutable $dt, \DateTimeZone $tz): array
    {
        // PHP 'Monday this week' gives the Monday of the current ISO week
        $monday = $dt->setTimezone($tz)->modify('Monday this week')->setTime(0, 0, 0);
        $sunday = $monday->modify('+6 days')->setTime(23, 59, 59);

        return [$monday, $sunday];
    }
}
