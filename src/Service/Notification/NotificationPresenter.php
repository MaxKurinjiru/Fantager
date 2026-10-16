<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Notification\Notification;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotificationPresenter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{
     *     id: int|null,
     *     type: string,
     *     title: string,
     *     body: string,
     *     is_read: bool,
     *     created_at: string
     * }
     */
    public function present(Notification $notification, ?string $locale = null): array
    {
        return [
            'id' => $notification->getId(),
            'type' => $notification->getType()->value,
            'title' => $this->render(
                $notification->getTitleKey(),
                $notification->getTitleParams(),
                $notification->getTitle(),
                $locale,
            ),
            'body' => $this->render(
                $notification->getBodyKey(),
                $notification->getBodyParams(),
                $notification->getBody(),
                $locale,
            ),
            'is_read' => $notification->isRead(),
            'created_at' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function render(?string $key, array $params, ?string $legacy, ?string $locale): string
    {
        if (null === $key || '' === $key) {
            return $legacy ?? '';
        }

        return $this->translator->trans(
            $key,
            $this->localizeParams($params, $locale),
            'messages',
            $locale,
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, string>
     */
    private function localizeParams(array $params, ?string $locale): array
    {
        $normalized = [];
        foreach ($params as $key => $value) {
            $bare = trim((string) $key, '%');
            $stringValue = (string) $value;
            $normalized['%'.$bare.'%'] = match ($bare) {
                'facility' => $this->translateFacility($stringValue, $locale),
                'race' => $this->translateRace($stringValue, $locale),
                'status', 'status_text' => $this->translateSeasonStatus($stringValue, $locale),
                default => $stringValue,
            };
        }

        return $normalized;
    }

    private function translateFacility(string $facility, ?string $locale): string
    {
        if ('' === $facility) {
            return $facility;
        }

        return $this->translator->trans(
            'hq.facilities_list.'.$facility.'.name',
            [],
            'messages',
            $locale,
        );
    }

    private function translateRace(string $race, ?string $locale): string
    {
        if ('' === $race || '—' === $race) {
            return $race;
        }

        return $this->translator->trans(
            'heroes.race_'.$race,
            [],
            'messages',
            $locale,
        );
    }

    private function translateSeasonStatus(string $status, ?string $locale): string
    {
        $key = match ($status) {
            'promoted', 'relegated', 'maintained' => $status,
            'retained' => 'maintained',
            default => $status,
        };

        return $this->translator->trans(
            'activity.season_status.'.$key,
            [],
            'messages',
            $locale,
        );
    }
}
