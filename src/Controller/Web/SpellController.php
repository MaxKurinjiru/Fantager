<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Enum\School;
use App\Repository\Hero\HeroRepository;
use App\Service\Spell\SpellService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class SpellController extends AbstractController
{
    public function __construct(
        private readonly SpellService $spellService,
        private readonly HeroRepository $heroRepository,
    ) {
    }

    #[Route('/app/spells', name: 'app_spells', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $heroId = $request->query->get('hero_id');
        if ($heroId) {
            return $this->redirectToRoute('app_heroes_detail', [
                'id' => $heroId,
                'tab' => 'spells',
            ]);
        }

        return $this->redirectToRoute('app_heroes');
    }

    #[Route('/app/academy', name: 'app_academy', methods: ['GET'])]
    public function academy(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Filters
        $schoolValue = $request->query->get('school');
        $school = $schoolValue ? School::tryFrom($schoolValue) : null;

        $tierValue = $request->query->get('tier');
        $tier = (null !== $tierValue && '' !== $tierValue) ? (int) $tierValue : null;

        // Selected Hero context for learning
        $heroId = $request->query->get('hero_id') ? (int) $request->query->get('hero_id') : null;
        $selectedHero = null;
        $masteries = [];
        $knownSpellIds = [];

        if ($heroId) {
            $selectedHero = $this->heroRepository->findOneBy(['id' => $heroId, 'team' => $team]);
            if ($selectedHero) {
                foreach ($selectedHero->getSchoolMasteries() as $sm) {
                    $masteries[$sm->getSchool()->value] = $sm->getMasteryTier();
                }
                foreach ($selectedHero->getHeroSpells() as $hs) {
                    $knownSpellIds[] = $hs->getSpell()->getId();
                }
            }
        }

        $spells = $this->spellService->listLibrary($school, $tier);
        $heroes = $this->heroRepository->findCombatantsByTeam($team);

        return $this->render('spell/academy.html.twig', [
            'team' => $team,
            'spells' => $spells,
            'heroes' => $heroes,
            'selected_hero' => $selectedHero,
            'masteries' => $masteries,
            'known_spell_ids' => $knownSpellIds,
            'selected_school' => $school ? $school->value : null,
            'selected_tier' => $tier,
            'schools' => School::cases(),
        ]);
    }
}
