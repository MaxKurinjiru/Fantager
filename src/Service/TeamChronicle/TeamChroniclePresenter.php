<?php

declare(strict_types=1);

namespace App\Service\TeamChronicle;

use App\Entity\Team\Team;
use App\Entity\Team\TeamChronicle;
use App\Enum\ChronicleCategory;
use App\Enum\ChronicleEventType;
use App\Repository\Team\TeamChronicleRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class TeamChroniclePresenter
{
    public function __construct(
        private readonly TeamChronicleRepository $teamChronicleRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return list<array{
     *     id: int|null,
     *     type: string,
     *     type_label: string,
     *     category: string,
     *     icon: string,
     *     message: string,
     *     created_at: \DateTimeImmutable,
     *     processed_at: ?\DateTimeImmutable
     * }>
     */
    public function presentRecentForTeam(Team $team, int $limit = 5, ?string $locale = null): array
    {
        $entries = $this->teamChronicleRepository->findRecentByTeam($team, $limit);

        return array_map(
            fn (TeamChronicle $entry): array => $this->presentEntry($entry, $locale),
            $entries,
        );
    }

    /**
     * @return list<array{
     *     id: int|null,
     *     type: string,
     *     type_label: string,
     *     category: string,
     *     icon: string,
     *     message: string,
     *     created_at: \DateTimeImmutable,
     *     processed_at: ?\DateTimeImmutable
     * }>
     */
    public function presentFilteredForTeam(
        Team $team,
        ?ChronicleEventType $type = null,
        ?ChronicleCategory $category = null,
        ?string $sort = 'date-desc',
        ?int $limit = null,
        ?string $locale = null,
    ): array {
        $entries = $this->teamChronicleRepository->findByTeamFiltered(
            $team,
            $type,
            $category,
            $sort,
            $limit,
        );

        return array_map(
            fn (TeamChronicle $entry): array => $this->presentEntry($entry, $locale),
            $entries,
        );
    }

    /**
     * @return array{
     *     id: int|null,
     *     type: string,
     *     type_label: string,
     *     category: string,
     *     icon: string,
     *     message: string,
     *     created_at: \DateTimeImmutable,
     *     processed_at: ?\DateTimeImmutable
     * }
     */
    public function presentEntry(TeamChronicle $entry, ?string $locale = null): array
    {
        $type = $entry->getType();
        $params = $entry->getSubjectParams();

        if (ChronicleEventType::SeasonEnded === $type && isset($params['status'])) {
            $params['status'] = $this->translator->trans(
                'activity.season_status.'.$params['status'],
                [],
                'messages',
                $locale,
            );
        }

        if (ChronicleEventType::SummonCompleted === $type && isset($params['race'])) {
            $raceKey = 'heroes.race_'.$params['race'];
            $params['race'] = $this->translator->trans($raceKey, [], 'messages', $locale);
        }

        $transParams = $this->normalizeTransParams($params);

        return [
            'id' => $entry->getId(),
            'type' => $type->value,
            'type_label' => $this->translator->trans(
                'activity.type.'.$type->value,
                [],
                'messages',
                $locale,
            ),
            'category' => $this->resolveCategory($type)->value,
            'icon' => $this->iconForType($type),
            'message' => $this->translator->trans(
                $entry->getSubjectKey(),
                $transParams,
                'messages',
                $locale,
            ),
            'created_at' => $entry->getCreatedAt(),
            'processed_at' => $entry->getProcessedAt(),
        ];
    }

    /**
     * @param array<string, string> $params
     *
     * @return array<string, string>
     */
    private function normalizeTransParams(array $params): array
    {
        $normalized = [];
        foreach ($params as $key => $value) {
            $normalizedKey = str_starts_with($key, '%') && str_ends_with($key, '%')
                ? $key
                : '%'.$key.'%';
            $normalized[$normalizedKey] = $value;
        }

        return $normalized;
    }

    public function resolveCategory(ChronicleEventType $type): ChronicleCategory
    {
        foreach ([
            ChronicleCategory::Ownership,
            ChronicleCategory::Competition,
            ChronicleCategory::Roster,
            ChronicleCategory::Economy,
        ] as $category) {
            $types = $category->types();
            if (null !== $types && in_array($type, $types, true)) {
                return $category;
            }
        }

        return ChronicleCategory::All;
    }

    public function iconForType(ChronicleEventType $type): string
    {
        return match ($type) {
            ChronicleEventType::TeamEstablished => '🏛️',
            ChronicleEventType::PlayerJoined => '👤',
            ChronicleEventType::PlayerReleased => '🚪',
            ChronicleEventType::BattleWin => '🏅',
            ChronicleEventType::BattleLoss => '💀',
            ChronicleEventType::BattleDraw => '🤝',
            ChronicleEventType::HeroLevelup => '⬆️',
            ChronicleEventType::HeroDied => '⚰️',
            ChronicleEventType::HeroRetired => '🌅',
            ChronicleEventType::TrainingCompleted => '💪',
            ChronicleEventType::ItemPurchased => '🛒',
            ChronicleEventType::ItemSold => '💰',
            ChronicleEventType::DungeonCompleted => '🗺️',
            ChronicleEventType::SummonCompleted => '🌀',
            ChronicleEventType::SeasonEnded => '🏆',
        };
    }
}
