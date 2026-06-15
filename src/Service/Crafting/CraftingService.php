<?php

declare(strict_types=1);

namespace App\Service\Crafting;

use App\Entity\Crafting\CraftingQueue;
use App\Entity\Crafting\CraftingRecipe;
use App\Entity\Item\Item;
use App\Entity\Team\Team;
use App\Enum\CraftingStatus;
use App\Enum\FacilityType;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Enum\ItemCategory;
use App\Enum\ItemRarity;
use App\Enum\ItemSlotType;
use App\Enum\ItemStatus;
use App\Repository\Crafting\CraftingQueueRepository;
use App\Repository\Crafting\CraftingRecipeRepository;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Service\Economy\EconomyService;
use Doctrine\ORM\EntityManagerInterface;

class CraftingService
{
    public function __construct(
        private readonly CraftingRecipeRepository $recipeRepository,
        private readonly CraftingQueueRepository $queueRepository,
        private readonly HeadquartersRepository $hqRepository,
        private readonly EconomyService $economyService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return list<CraftingRecipe> */
    public function listRecipes(): array
    {
        return $this->recipeRepository->findBy([], ['requiredForgeLevel' => 'ASC', 'id' => 'ASC']);
    }

    /** @return list<CraftingQueue> */
    public function listQueueForTeam(Team $team): array
    {
        return $this->queueRepository->findBy(['team' => $team], ['completesAt' => 'ASC']);
    }

    public function startJob(Team $team, int $recipeId, \DateTimeImmutable $now): CraftingQueue
    {
        /** @var CraftingRecipe|null $recipe */
        $recipe = $this->recipeRepository->find($recipeId);
        if (null === $recipe) {
            throw new \DomainException('Recipe not found.');
        }

        $forgeLevel = $this->getForgeLevel($team);
        if ($forgeLevel < $recipe->getRequiredForgeLevel()) {
            throw new \DomainException(sprintf(
                'Forge level %d required. Your forge is level %d.',
                $recipe->getRequiredForgeLevel(),
                $forgeLevel
            ));
        }

        if ($team->getGold() < $recipe->getGoldCost()) {
            throw new \DomainException(sprintf(
                'Insufficient gold. Required: %d, available: %d.',
                $recipe->getGoldCost(),
                $team->getGold()
            ));
        }

        $essenceType = $recipe->getEssenceCostType();
        if (null !== $essenceType && $recipe->getEssenceCostAmount() > 0) {
            $available = $this->getEssenceAmount($team, $essenceType);
            if ($available < $recipe->getEssenceCostAmount()) {
                throw new \DomainException(sprintf(
                    'Insufficient %s essence. Required: %d, available: %d.',
                    $essenceType->value,
                    $recipe->getEssenceCostAmount(),
                    $available
                ));
            }
        }

        if ($recipe->getGoldCost() > 0) {
            $this->economyService->deductGold(
                $team,
                $recipe->getGoldCost(),
                FinancialRecordType::CraftingCost,
                FinancialRecordActor::Active,
                ['recipe_id' => $recipeId]
            );
        }

        if (null !== $essenceType && $recipe->getEssenceCostAmount() > 0) {
            $this->deductEssence($team, $essenceType, $recipe->getEssenceCostAmount());
        }

        $completesAt = $now->modify(sprintf('+%d seconds', $recipe->getCraftingTime()));

        $job = new CraftingQueue();
        $job->setTeam($team);
        $job->setRecipe($recipe);
        $job->setStatus(CraftingStatus::InProgress);
        $job->setStartedAt($now);
        $job->setCompletesAt($completesAt);

        $this->em->persist($job);
        $this->economyService->flush();

        return $job;
    }

    public function cancelJob(Team $team, int $queueId): void
    {
        /** @var CraftingQueue|null $job */
        $job = $this->queueRepository->find($queueId);
        if (null === $job || $job->getTeam()->getId() !== $team->getId()) {
            throw new \DomainException('Crafting job not found.');
        }

        if (CraftingStatus::InProgress !== $job->getStatus() && CraftingStatus::Pending !== $job->getStatus()) {
            throw new \DomainException('Only active crafting jobs can be cancelled.');
        }

        $job->setStatus(CraftingStatus::Cancelled);
        $this->em->flush();
    }

    /**
     * Resolve all crafting jobs whose completion time has passed.
     *
     * @return array{completed: int, failed: int}
     */
    public function processDueJobs(\DateTimeImmutable $now): array
    {
        $jobs = $this->queueRepository->findDueJobs($now);
        $completed = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            $recipe = $job->getRecipe();
            $successRate = (float) $recipe->getSuccessRateBase();
            $roll = random_int(1, 10000) / 10000.0;

            if ($roll <= $successRate) {
                $item = $this->createCraftedItem($job->getTeam(), $recipe);
                $this->em->persist($item);
                $job->setStatus(CraftingStatus::Completed);
                ++$completed;
            } else {
                $job->setStatus(CraftingStatus::Failed);
                ++$failed;
            }
        }

        if ($completed > 0 || $failed > 0) {
            $this->em->flush();
        }

        return ['completed' => $completed, 'failed' => $failed];
    }

    private function getForgeLevel(Team $team): int
    {
        $hq = $this->hqRepository->findOneBy(['team' => $team]);
        if (null === $hq) {
            return 0;
        }

        foreach ($hq->getFacilities() as $facility) {
            if (FacilityType::Forge === $facility->getType()) {
                return $facility->getLevel();
            }
        }

        return 0;
    }

    private function getEssenceAmount(Team $team, ItemRarity $rarity): int
    {
        return match ($rarity) {
            ItemRarity::Common => $team->getEssenceCommon(),
            ItemRarity::Uncommon => $team->getEssenceUncommon(),
            ItemRarity::Rare => $team->getEssenceRare(),
            ItemRarity::Epic => $team->getEssenceEpic(),
            ItemRarity::Legendary => $team->getEssenceLegendary(),
            ItemRarity::Mythic => $team->getEssenceMythic(),
        };
    }

    private function deductEssence(Team $team, ItemRarity $rarity, int $amount): void
    {
        match ($rarity) {
            ItemRarity::Common => $team->setEssenceCommon($team->getEssenceCommon() - $amount),
            ItemRarity::Uncommon => $team->setEssenceUncommon($team->getEssenceUncommon() - $amount),
            ItemRarity::Rare => $team->setEssenceRare($team->getEssenceRare() - $amount),
            ItemRarity::Epic => $team->setEssenceEpic($team->getEssenceEpic() - $amount),
            ItemRarity::Legendary => $team->setEssenceLegendary($team->getEssenceLegendary() - $amount),
            ItemRarity::Mythic => $team->setEssenceMythic($team->getEssenceMythic() - $amount),
        };
    }

    private function createCraftedItem(Team $team, CraftingRecipe $recipe): Item
    {
        $item = new Item();
        $item->setOwnerTeam($team);
        $item->setCategory($recipe->getResultItemCategory());
        $item->setRarity($recipe->getResultItemRarity());
        $item->setSlotType($this->slotForCategory($recipe->getResultItemCategory()));
        $item->setName($this->generateItemName($recipe));
        $item->setBonuses($this->defaultBonusesForRarity($recipe->getResultItemRarity()));
        $item->setSpecialEffects([]);
        $item->setDurability(100);
        $item->setStatus(ItemStatus::Available);

        return $item;
    }

    private function slotForCategory(ItemCategory $category): ItemSlotType
    {
        return match ($category) {
            ItemCategory::Weapon => ItemSlotType::MainHand,
            ItemCategory::Shield => ItemSlotType::OffHand,
            ItemCategory::SpellAccelerator => ItemSlotType::OffHand,
            ItemCategory::Armor => ItemSlotType::Body,
            ItemCategory::Accessory => ItemSlotType::Amulet,
            ItemCategory::Material => ItemSlotType::MainHand,
        };
    }

    private function generateItemName(CraftingRecipe $recipe): string
    {
        return sprintf(
            'Crafted %s (%s)',
            ucfirst(str_replace('_', ' ', $recipe->getResultItemCategory()->value)),
            ucfirst($recipe->getResultItemRarity()->value)
        );
    }

    /** @return array<string, int> */
    private function defaultBonusesForRarity(ItemRarity $rarity): array
    {
        $base = match ($rarity) {
            ItemRarity::Common => 1,
            ItemRarity::Uncommon => 2,
            ItemRarity::Rare => 3,
            ItemRarity::Epic => 5,
            ItemRarity::Legendary => 8,
            ItemRarity::Mythic => 12,
        };

        return ['str' => $base, 'dex' => $base];
    }
}
