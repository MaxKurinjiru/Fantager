<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Enum\School;
use App\Repository\Hero\HeroRepository;
use App\Repository\Hero\HeroSpellRepository;
use App\Repository\Spell\SpellRepository;
use App\Service\Spell\SpellService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SpellController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly SpellService $spellService,
        private readonly HeroRepository $heroRepository,
        private readonly HeroSpellRepository $heroSpellRepository,
        private readonly SpellRepository $spellRepository,
    ) {
    }

    /** Global spell library (filterable by school and tier). */
    #[Route('/api/v1/spells', name: 'api_spells_library', methods: ['GET'])]
    public function library(Request $request): JsonResponse
    {
        $schoolValue = $request->query->get('school');
        $school = $schoolValue ? School::tryFrom($schoolValue) : null;

        $tier = $request->query->has('tier') ? (int) $request->query->get('tier') : null;

        $spells = $this->spellService->listLibrary($school, $tier);

        return $this->json(array_map($this->spellService->serializeSpell(...), $spells));
    }

    /** Spells known by a specific hero. */
    #[Route('/api/v1/heroes/{heroId}/spells', name: 'api_hero_spells', methods: ['GET'], requirements: ['heroId' => '\d+'])]
    public function heroSpells(int $heroId): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        $hero = $this->heroRepository->findOneBy(['id' => $heroId, 'team' => $team]);
        if (null === $hero) {
            return $this->jsonError('error.hero_not_found', 404);
        }

        $heroSpells = $this->spellService->listForHero($hero);

        return $this->json(array_map($this->spellService->serializeHeroSpell(...), $heroSpells));
    }

    /**
     * Learn a spell for a hero.
     * Body: { "spell_id": 3 }.
     */
    #[Route('/api/v1/heroes/{heroId}/spells/learn', name: 'api_hero_spell_learn', methods: ['POST'], requirements: ['heroId' => '\d+'])]
    public function learn(int $heroId, Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        $hero = $this->heroRepository->findOneBy(['id' => $heroId, 'team' => $team]);
        if (null === $hero) {
            return $this->jsonError('error.hero_not_found', 404);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $spellId = (int) ($body['spell_id'] ?? 0);
        if (0 === $spellId) {
            return $this->jsonError('error.field_spell_id_required', 400);
        }

        $spell = $this->spellRepository->find($spellId);
        if (null === $spell) {
            return $this->jsonError('error.spell_not_found', 404);
        }

        try {
            $heroSpell = $this->spellService->learn($hero, $spell, $team);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 422);
        }

        return $this->json($this->spellService->serializeHeroSpell($heroSpell), 201);
    }

    /**
     * Equip a known spell to a slot.
     * Body: { "hero_spell_id": 7, "slot": 1 }.
     */
    #[Route('/api/v1/heroes/{heroId}/spells/equip', name: 'api_hero_spell_equip', methods: ['POST'], requirements: ['heroId' => '\d+'])]
    public function equip(int $heroId, Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        $hero = $this->heroRepository->findOneBy(['id' => $heroId, 'team' => $team]);
        if (null === $hero) {
            return $this->jsonError('error.hero_not_found', 404);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $heroSpellId = (int) ($body['hero_spell_id'] ?? 0);
        if (0 === $heroSpellId) {
            return $this->jsonError('error.field_hero_spell_id_required', 400);
        }

        $heroSpell = $this->heroSpellRepository->findOneBy(['id' => $heroSpellId, 'hero' => $hero]);
        if (null === $heroSpell) {
            return $this->jsonError('error.hero_spell_not_found', 404);
        }

        $slot = (int) ($body['slot'] ?? 0);
        if (0 === $slot) {
            return $this->jsonError('error.field_slot_required', 400);
        }

        try {
            $this->spellService->equip($heroSpell, $slot);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 422);
        }

        return $this->json($this->spellService->serializeHeroSpell($heroSpell));
    }

    /**
     * Unequip a spell from its slot.
     * Body: { "hero_spell_id": 7 }.
     */
    #[Route('/api/v1/heroes/{heroId}/spells/unequip', name: 'api_hero_spell_unequip', methods: ['POST'], requirements: ['heroId' => '\d+'])]
    public function unequip(int $heroId, Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        $hero = $this->heroRepository->findOneBy(['id' => $heroId, 'team' => $team]);
        if (null === $hero) {
            return $this->jsonError('error.hero_not_found', 404);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $heroSpellId = (int) ($body['hero_spell_id'] ?? 0);
        if (0 === $heroSpellId) {
            return $this->jsonError('error.field_hero_spell_id_required', 400);
        }

        $heroSpell = $this->heroSpellRepository->findOneBy(['id' => $heroSpellId, 'hero' => $hero]);
        if (null === $heroSpell) {
            return $this->jsonError('error.hero_spell_not_found', 404);
        }

        try {
            $this->spellService->unequip($heroSpell);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 422);
        }

        return $this->json($this->spellService->serializeHeroSpell($heroSpell));
    }

    private function getPlayerTeam(): ?\App\Entity\Team\Team
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getTeam();
    }
}
