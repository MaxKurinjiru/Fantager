<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Entity\Formation\Formation;
use App\Entity\Hero\Hero;
use App\Enum\HeroStatus;
use App\Repository\Formation\FormationRepository;
use App\Repository\Hero\HeroRepository;
use App\Service\Formation\FixtureFormationService;
use App\Service\Formation\FormationTacticsNormalizer;
use App\Service\Formation\MatchLineupService;
use App\Service\Spell\SpellService;
use App\Service\Translation\UserMessageTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class FormationController extends AbstractController
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly FormationRepository $formationRepository,
        private readonly FixtureFormationService $fixtureFormationService,
        private readonly MatchLineupService $matchLineupService,
        private readonly FormationTacticsNormalizer $tacticsNormalizer,
        private readonly SpellService $spellService,
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    #[Route('/app/formation', name: 'app_formation', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', $this->userMessages->trans('error.no_team'));

            return $this->redirectToRoute('app_home');
        }

        $heroes = array_values(array_filter(
            $this->heroRepository->findCombatantsByTeam($team),
            static fn ($hero) => HeroStatus::Dead !== $hero->getStatus() && !$hero->getStatus()->isReserved(),
        ));
        $formations = $this->formationRepository->findSavedByTeam($team);

        $fixture = null;
        $fixtureAssignment = null;
        $fixtureLineup = null;
        $fixtureId = $request->query->get('fixture_id');
        if (null !== $fixtureId && '' !== $fixtureId && is_numeric($fixtureId)) {
            $fixture = $this->fixtureFormationService->findFixtureForTeam((int) $fixtureId, $team);
            if (null !== $fixture) {
                try {
                    $fixtureAssignment = $this->fixtureFormationService->getAssignmentState($fixture, $team);
                    $fixtureLineup = $this->matchLineupService->preview(
                        $team,
                        $this->fixtureFormationService->getAssignedFormation($fixture, $team),
                    );
                } catch (\DomainException) {
                    $fixture = null;
                    $fixtureAssignment = null;
                    $fixtureLineup = null;
                }
            }
        }

        return $this->render('formation/index.html.twig', [
            'team' => $team,
            'heroes' => $heroes,
            'formations' => $formations,
            'fixture' => $fixture,
            'fixture_assignment' => $fixtureAssignment,
            'fixture_lineup' => $fixtureLineup,
            'slot_tactics' => $this->slotTacticsForPage($fixtureAssignment, $formations, $request->query->get('formation_id')),
            'hero_spells' => $this->equippedSpellsByHeroId($heroes),
        ]);
    }

    /**
     * @param array<string, mixed>|null $fixtureAssignment
     * @param list<Formation>           $formations
     *
     * @return array<string, array{target_order: list<string>, fallback: string, spell_priorities: list<array<string, mixed>>}>
     */
    private function slotTacticsForPage(?array $fixtureAssignment, array $formations, mixed $formationId): array
    {
        if (null !== $fixtureAssignment) {
            $source = $fixtureAssignment['assigned_formation'] ?? $fixtureAssignment['effective_formation'] ?? null;
            if (is_array($source) && isset($source['slots']) && is_iterable($source['slots'])) {
                return $this->tacticsNormalizer->initialUiStateFromSlots($source['slots']);
            }

            return $this->tacticsNormalizer->emptyUiState();
        }

        $selected = $this->resolveSelectedFormation($formations, $formationId);
        if (null !== $selected) {
            return $this->tacticsNormalizer->initialUiStateFromSlots($selected->getSlots());
        }

        return $this->tacticsNormalizer->emptyUiState();
    }

    /**
     * @param list<Formation> $formations
     */
    private function resolveSelectedFormation(array $formations, mixed $formationId): ?Formation
    {
        if (null !== $formationId && '' !== $formationId && 'new' !== $formationId && is_numeric($formationId)) {
            $id = (int) $formationId;
            foreach ($formations as $formation) {
                if ($formation->getId() === $id) {
                    return $formation;
                }
            }

            return null;
        }

        foreach ($formations as $formation) {
            if ($formation->isDefault()) {
                return $formation;
            }
        }

        return $formations[0] ?? null;
    }

    /**
     * @param list<Hero> $heroes
     *
     * @return array<int, list<array{id: int, name: string, type: string, requires_magical_weapon: bool, castable: bool}>>
     */
    private function equippedSpellsByHeroId(array $heroes): array
    {
        $map = [];
        foreach ($heroes as $hero) {
            $heroId = $hero->getId();
            if (null === $heroId) {
                continue;
            }

            $equipped = [];
            $canCastOffensive = $this->spellService->heroCanCastOffensiveSpells($hero);
            foreach ($this->spellService->listForHero($hero) as $heroSpell) {
                if (!$heroSpell->isEquipped()) {
                    continue;
                }
                $spell = $heroSpell->getSpell();
                $spellId = $spell->getId();
                if (null === $spellId) {
                    continue;
                }
                $needsWeapon = $spell->requiresMagicalWeapon();
                $equipped[] = [
                    'id' => $spellId,
                    'name' => $this->spellService->displayName($spell),
                    'type' => $spell->getType()->value,
                    'requires_magical_weapon' => $needsWeapon,
                    'castable' => !$needsWeapon || $canCastOffensive,
                ];
            }
            $map[$heroId] = $equipped;
        }

        return $map;
    }
}
