<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Repository\Kingdom\KingdomRepository;
use App\Service\Calendar\CalendarService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class CalendarController extends AbstractController
{
    public function __construct(
        private readonly CalendarService $calendarService,
        private readonly KingdomRepository $kingdomRepository,
    ) {
    }

    #[Route('/api/v1/kingdom/{id}/calendar', name: 'api_kingdom_calendar', methods: ['GET'])]
    public function getFeed(int $id, Request $request): JsonResponse
    {
        /** @var \App\Entity\Kingdom\Kingdom|null $kingdom */
        $kingdom = $this->kingdomRepository->find($id);
        if (null === $kingdom) {
            return $this->json(['error' => 'Kingdom not found.'], 404);
        }

        $startStr = $request->query->get('start');
        $endStr = $request->query->get('end');
        $teamIdStr = $request->query->get('teamId');
        $includeSystem = 'true' === $request->query->get('include_system');

        try {
            $start = $startStr ? new \DateTimeImmutable($startStr) : new \DateTimeImmutable('-1 day');
            $end = $endStr ? new \DateTimeImmutable($endStr) : new \DateTimeImmutable('+7 days');
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid date format. Please use ISO-8601 (e.g. 2026-06-08T00:00:00Z).'], 400);
        }

        $teamId = $teamIdStr ? (int) $teamIdStr : null;

        $feed = $this->calendarService->getCalendarFeed($kingdom, $start, $end, $teamId);

        // Filter out system-only events if not requested
        if (!$includeSystem) {
            $feed = array_values(array_filter($feed, static function (array $item): bool {
                return 'system_only' !== $item['visibility'];
            }));
        }

        return $this->json($feed);
    }
}
