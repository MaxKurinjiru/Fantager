<?php

declare(strict_types=1);

namespace App\Service\Community;

use App\Entity\Auth\User;
use App\Entity\Team\Team;
use App\Exception\UserFacingException;
use App\Repository\Hero\HeroRepository;
use App\Service\League\LeagueService;
use Doctrine\ORM\EntityManagerInterface;

class PlayerProfileService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LeagueService $leagueService,
        private readonly HeroRepository $heroRepository,
    ) {
    }

    /**
     * Returns a public player/team profile visible to other players in the same kingdom.
     * Sensitive data (gold, essence, debt, crisis) is never included.
     *
     * @return array<string, mixed>
     *
     * @throws UserFacingException when access is denied or profile does not exist
     */
    public function getProfile(User $subject, User $viewer): array
    {
        $team = $subject->getTeam();
        if (null === $team) {
            throw new UserFacingException('error.player_profile_not_found');
        }

        return $this->getProfileByTeam($team, $viewer);
    }

    /**
     * Returns a public team profile by Team ID.
     *
     * @return array<string, mixed>
     */
    public function getProfileByTeam(Team $team, User $viewer): array
    {
        // Cross-kingdom access is not allowed
        if ($team->getKingdom() !== $viewer->getKingdom()) {
            throw new UserFacingException('error.access_denied');
        }

        $subject = $team->getUser();
        $isOwnProfile = $subject && ($subject->getId() === $viewer->getId());

        // League standing
        $leagueData = null;
        $kingdom = $team->getKingdom();
        $season = $this->leagueService->getCurrentSeason($kingdom);
        if (null !== $season) {
            $standing = $this->leagueService->findStandingForTeam($season, $team);
            if (null !== $standing) {
                $form = $this->leagueService->getTeamForm($team, $season, 5);
                $group = $standing->getGroup();

                // Compute position within the group
                $sortedStandings = $this->leagueService->getSortedStandings($group);
                $position = 1;
                foreach ($sortedStandings as $index => $s) {
                    if ($s->getTeam()->getId() === $team->getId()) {
                        $position = $index + 1;
                        break;
                    }
                }

                $leagueData = [
                    'tier_name' => $group->getTier()->getTierName(),
                    'group_name' => $group->getGroupName(),
                    'position' => $position,
                    'points' => $standing->getPoints(),
                    'played' => $standing->getPlayed(),
                    'wins' => $standing->getWins(),
                    'draws' => $standing->getDraws(),
                    'losses' => $standing->getLosses(),
                    'goal_difference' => $standing->getGoalDifference(),
                    'form' => $form,
                ];
            }
        }

        // Roster counts (public info only)
        $combatantCount = count($this->heroRepository->findCombatantsByTeam($team));
        $trainerCount = count($this->heroRepository->findTrainersByTeam($team));

        return [
            'user' => $subject ? [
                'id' => $subject->getId(),
                'display_name' => $subject->getDisplayName(),
                'member_since' => $subject->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ] : null,
            'team' => [
                'id' => $team->getId(),
                'name' => $team->getName(),
                'emblem' => $team->getEmblem() ?: '🛡️',
                'colors' => $team->getColors(),
                'fan_base' => $team->getFanBase(),
                'reputation' => $team->getReputation(),
                'combatant_count' => $combatantCount,
                'trainer_count' => $trainerCount,
            ],
            'league' => $leagueData,
            'is_own_profile' => $isOwnProfile,
            'can_message' => $subject && !$isOwnProfile,
        ];
    }

    /**
     * Finds a verified, non-NPC user by ID within the viewer's kingdom.
     *
     * @throws UserFacingException when the user is not found or belongs to a different kingdom
     */
    public function findSubject(User $viewer, int $userId): User
    {
        /** @var User|null $subject */
        $subject = $this->em->getRepository(User::class)->find($userId);

        if (null === $subject || !$subject->isVerified()) {
            throw new UserFacingException('error.player_profile_not_found');
        }

        return $subject;
    }

    /**
     * Finds a team by ID within the viewer's kingdom.
     *
     * @throws UserFacingException when the team is not found or belongs to a different kingdom
     */
    public function findTeam(User $viewer, int $teamId): Team
    {
        /** @var Team|null $team */
        $team = $this->em->getRepository(Team::class)->find($teamId);

        if (null === $team) {
            throw new UserFacingException('error.player_profile_not_found');
        }

        if ($team->getKingdom() !== $viewer->getKingdom()) {
            throw new UserFacingException('error.access_denied');
        }

        return $team;
    }
}
