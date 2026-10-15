<?php

declare(strict_types=1);

namespace App\Tests\Service\FriendlyMatch;

use App\Entity\Auth\User;
use App\Entity\FriendlyMatch\FriendlyMatch;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\FriendlyMatchStatus;
use App\Exception\UserFacingException;
use App\Repository\FriendlyMatch\FriendlyMatchRepository;
use App\Repository\Formation\FormationRepository;
use App\Service\FriendlyMatch\FriendlyMatchService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FriendlyMatchServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&FriendlyMatchRepository */
    private FriendlyMatchRepository $repoMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject&FormationRepository */
    private FormationRepository $formationRepoMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject&EntityManagerInterface */
    private EntityManagerInterface $emMock;

    private FriendlyMatchService $service;

    protected function setUp(): void
    {
        $this->repoMock = $this->createMock(FriendlyMatchRepository::class);
        $this->formationRepoMock = $this->createMock(FormationRepository::class);
        $this->emMock = $this->createMock(EntityManagerInterface::class);

        $this->service = new FriendlyMatchService(
            $this->repoMock,
            $this->formationRepoMock,
            $this->emMock,
        );
    }

    // -------------------------------------------------------------------------
    // listAvailableSlots — home cap
    // -------------------------------------------------------------------------

    public function testListAvailableSlotsRespectsHomeCap(): void
    {
        [$challenger, $opponent, $kingdom] = $this->createTwoPvpTeams();

        // Wed 2026-10-21 18:00 UTC (no TZ offset for simplicity)
        $now = new \DateTimeImmutable('2026-10-19T10:00:00Z');

        // Stub: challenger already has 1 home friendly this ISO week → cap reached for that week
        $this->repoMock
            ->method('countHomeInWeek')
            ->willReturnCallback(function (Team $team, \DateTimeImmutable $start, \DateTimeImmutable $end) use ($now): int {
                // First call will be for the week containing Wed 2026-10-21 → return cap hit (1)
                $weekOfSlot = (int) (new \DateTimeImmutable('2026-10-21'))->format('W');
                $weekOfStart = (int) $start->format('W');
                if ($weekOfSlot === $weekOfStart) {
                    return 1; // cap hit
                }

                return 0;
            });

        $this->repoMock->method('findTeamConflictAtTime')->willReturn(null);

        $slots = $this->service->listAvailableSlots($challenger, $opponent, $now);

        // Wed Oct 21 slot should be excluded; other slots (Sun, next Wed/Sun, etc.) may appear
        $slotDays = array_map(
            static fn(string $s): string => (new \DateTimeImmutable($s))->format('D'),
            $slots
        );

        // No slot from the week of Oct 21 on a Wednesday should be in the list
        $hasOct21 = in_array('2026-10-21', array_map(
            static fn(string $s): string => (new \DateTimeImmutable($s))->format('Y-m-d'),
            $slots
        ), true);
        $this->assertFalse($hasOct21, 'Home cap should exclude Wed Oct 21 slot');
    }

    public function testListAvailableSlotsRespectsInviteCutoff(): void
    {
        [$challenger, $opponent, $kingdom] = $this->createTwoPvpTeams();

        // Now is 6 hours before the Wed slot — cutoff is NOT open (needs > 6h)
        $now = new \DateTimeImmutable('2026-10-21T12:00:00Z'); // Wed slot at 18:00 UTC → 6h away

        $this->repoMock->method('countHomeInWeek')->willReturn(0);
        $this->repoMock->method('findTeamConflictAtTime')->willReturn(null);

        $slots = $this->service->listAvailableSlots($challenger, $opponent, $now);

        $hasOct21 = in_array('2026-10-21', array_map(
            static fn(string $s): string => (new \DateTimeImmutable($s))->format('Y-m-d'),
            $slots
        ), true);
        $this->assertFalse($hasOct21, 'Slot exactly at 6h cutoff should be excluded');
    }

    public function testListAvailableSlotsIncludesWedAndSunSlots(): void
    {
        [$challenger, $opponent, $kingdom] = $this->createTwoPvpTeams();

        // Now is Monday — next slots are Wed and Sun
        $now = new \DateTimeImmutable('2026-10-19T10:00:00Z');

        $this->repoMock->method('countHomeInWeek')->willReturn(0);
        $this->repoMock->method('findTeamConflictAtTime')->willReturn(null);

        $slots = $this->service->listAvailableSlots($challenger, $opponent, $now);

        $days = array_map(
            static fn(string $s): string => (new \DateTimeImmutable($s))->format('D'),
            $slots
        );
        $this->assertContains('Wed', $days, 'Wednesday slots should be offered');
        $this->assertContains('Sun', $days, 'Sunday slots should be offered');
    }

    public function testListAvailableSlotsThrowsWhenOpponentIsNpc(): void
    {
        $kingdom = $this->createKingdom(1);
        $challenger = $this->createTeam(1, $kingdom, true);
        $opponent = $this->createTeam(2, $kingdom, false); // NPC: no user

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.friendly_opponent_npc');

        $this->service->listAvailableSlots($challenger, $opponent, new \DateTimeImmutable('now'));
    }

    public function testListAvailableSlotsThrowsWhenSameTeam(): void
    {
        $kingdom = $this->createKingdom(1);
        $team = $this->createTeam(1, $kingdom, true);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.friendly_same_team');

        $this->service->listAvailableSlots($team, $team, new \DateTimeImmutable('now'));
    }

    // -------------------------------------------------------------------------
    // accept / decline / cancel
    // -------------------------------------------------------------------------

    public function testAcceptFailsWhenNotAwayTeam(): void
    {
        $kingdom = $this->createKingdom(1);
        $home = $this->createTeam(1, $kingdom, true);
        $away = $this->createTeam(2, $kingdom, true);
        $match = $this->createMatch($home, $away, FriendlyMatchStatus::Pending);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.friendly_not_participant');

        $this->service->accept($match, $home, new \DateTimeImmutable('now'));
    }

    public function testAcceptFailsWhenPastCutoff(): void
    {
        $kingdom = $this->createKingdom(1);
        $home = $this->createTeam(1, $kingdom, true);
        $away = $this->createTeam(2, $kingdom, true);

        // Scheduled 5 hours from now — cutoff not open
        $now = new \DateTimeImmutable('now');
        $scheduledAt = $now->modify('+5 hours');
        $match = $this->createMatch($home, $away, FriendlyMatchStatus::Pending, $scheduledAt);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.friendly_invite_cutoff');

        $this->service->accept($match, $away, $now);
    }

    public function testDeclineFailsWhenNotPending(): void
    {
        $kingdom = $this->createKingdom(1);
        $home = $this->createTeam(1, $kingdom, true);
        $away = $this->createTeam(2, $kingdom, true);
        $match = $this->createMatch($home, $away, FriendlyMatchStatus::Scheduled);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.friendly_not_pending');

        $this->service->decline($match, $away);
    }

    public function testCancelFailsAfterCancelCutoff(): void
    {
        $kingdom = $this->createKingdom(1);
        $home = $this->createTeam(1, $kingdom, true);
        $away = $this->createTeam(2, $kingdom, true);

        // 30 minutes until kickoff — past cancel cutoff
        $now = new \DateTimeImmutable('now');
        $scheduledAt = $now->modify('+30 minutes');
        $match = $this->createMatch($home, $away, FriendlyMatchStatus::Scheduled, $scheduledAt);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.friendly_cancel_cutoff');

        $this->service->cancel($match, $home, $now);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array{Team, Team, Kingdom} */
    private function createTwoPvpTeams(): array
    {
        $kingdom = $this->createKingdom(1);
        $challenger = $this->createTeam(1, $kingdom, true);
        $opponent = $this->createTeam(2, $kingdom, true);

        return [$challenger, $opponent, $kingdom];
    }

    private function createKingdom(int $id): Kingdom
    {
        $kingdom = new Kingdom();
        $this->setId($kingdom, $id);
        $kingdom->setTimezone('UTC');

        return $kingdom;
    }

    private function createTeam(int $id, Kingdom $kingdom, bool $withUser): Team
    {
        $team = new Team();
        $this->setId($team, $id);
        $team->setKingdom($kingdom);

        if ($withUser) {
            $user = new User();
            $team->setUser($user);
        }

        return $team;
    }

    private function createMatch(
        Team $home,
        Team $away,
        FriendlyMatchStatus $status,
        ?\DateTimeImmutable $scheduledAt = null,
    ): FriendlyMatch {
        $match = new FriendlyMatch();
        $match->setHomeTeam($home);
        $match->setAwayTeam($away);
        $match->setKingdom($home->getKingdom());
        $match->setScheduledAt($scheduledAt ?? new \DateTimeImmutable('+1 week'));
        $match->setStatus($status);

        return $match;
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}
