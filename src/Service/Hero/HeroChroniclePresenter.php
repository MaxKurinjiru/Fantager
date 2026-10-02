<?php

declare(strict_types=1);

namespace App\Service\Hero;

use App\Entity\Hero\Hero;
use App\Entity\Hero\HeroChronicle;
use App\Enum\HeroChronicleEventType;
use App\Repository\Hero\HeroChronicleRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class HeroChroniclePresenter
{
    public function __construct(
        private readonly HeroChronicleRepository $heroChronicleRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return list<array{
     *     id: int|null,
     *     type: string,
     *     icon: string,
     *     message: string,
     *     created_at: \DateTimeImmutable
     * }>
     */
    public function presentRecentForHero(Hero|int $hero, int $limit = 10, ?string $locale = null): array
    {
        $entries = $this->heroChronicleRepository->findRecentByHero($hero, $limit);

        return array_map(
            fn (HeroChronicle $entry): array => $this->presentEntry($entry, $locale),
            $entries,
        );
    }

    /**
     * @return array{
     *     id: int|null,
     *     type: string,
     *     icon: string,
     *     message: string,
     *     created_at: \DateTimeImmutable
     * }
     */
    public function presentEntry(HeroChronicle $entry, ?string $locale = null): array
    {
        $type = $entry->getType();
        $params = $entry->getSubjectParams();

        if (HeroChronicleEventType::TrainingCompleted === $type) {
            if (isset($params['attribute']) && '' !== $params['attribute']) {
                $params['attribute'] = $this->translator->trans(
                    'training.attr.'.$params['attribute'],
                    [],
                    'messages',
                    $locale,
                );
            } else {
                $params['attribute'] = '';
            }
        }

        if (HeroChronicleEventType::MasteryGained === $type && isset($params['mastery'])) {
            $mastery = $params['mastery'];

            // Try weapon style translation (e.g. weapon_style.staff)
            $masteryKey = 'weapon_style.'.$mastery;
            $translatedMastery = $this->translator->trans($masteryKey, [], 'messages', $locale);

            if ($translatedMastery === $masteryKey) {
                // Try magic school translation (e.g. school.fire)
                $masteryKey = 'school.'.$mastery;
                $translatedMastery = $this->translator->trans($masteryKey, [], 'messages', $locale);

                if ($translatedMastery === $masteryKey) {
                    // Try training attribute fallback
                    $masteryKey = 'training.attr.'.$mastery;
                    $translatedMastery = $this->translator->trans($masteryKey, [], 'messages', $locale);

                    if ($translatedMastery === $masteryKey) {
                        $translatedMastery = $mastery;
                    }
                }
            }
            $params['mastery'] = $translatedMastery;
        }

        $transParams = [];
        foreach ($params as $key => $value) {
            $normalizedKey = str_starts_with($key, '%') && str_ends_with($key, '%')
                ? $key
                : '%'.$key.'%';
            $transParams[$normalizedKey] = $value;
        }

        return [
            'id' => $entry->getId(),
            'type' => $type->value,
            'icon' => $this->iconForType($type),
            'message' => $this->translator->trans(
                $entry->getSubjectKey(),
                $transParams,
                'messages',
                $locale,
            ),
            'created_at' => $entry->getCreatedAt(),
        ];
    }

    public function iconForType(HeroChronicleEventType $type): string
    {
        return match ($type) {
            HeroChronicleEventType::Summoned => '🌀',
            HeroChronicleEventType::Transferred => '🤝',
            HeroChronicleEventType::MatchPlayed => '⚔️',
            HeroChronicleEventType::LevelUp => '⬆️',
            HeroChronicleEventType::MasteryGained => '🎓',
            HeroChronicleEventType::TrainingCompleted => '💪',
            HeroChronicleEventType::SpellLearned => '📜',
            HeroChronicleEventType::Injured => '🩹',
            HeroChronicleEventType::Recovered => '❤️',
            HeroChronicleEventType::Retired => '🌅',
            HeroChronicleEventType::Died => '⚰️',
        };
    }
}
