<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Service\Kingdom\KingdomService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class KingdomController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly KingdomService $kingdomService,
    ) {
    }

    #[Route('/api/v1/kingdoms', name: 'api_kingdoms', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $kingdoms = $this->kingdomService->listWithCapacity();

        $data = array_map(static function (array $entry): array {
            $k = $entry['kingdom'];

            return [
                'id' => $k->getId(),
                'name' => $k->getName(),
                'language' => $k->getLanguage(),
                'timezone' => $k->getTimezone(),
                'pace_preset' => $k->getPacePreset()->value,
                'training_speed' => $k->getTrainingSpeed(),
                'recovery_speed' => $k->getRecoverySpeed(),
                'aging_speed' => $k->getAgingSpeed(),
                'construction_speed' => $k->getConstructionSpeed(),
                'economy_scale' => $k->getEconomyScale(),
                'fan_pace' => $k->getFanPace(),
                'summon_cap' => $k->getSummonCap(),
                'season_length' => $k->getSeasonLength(),
                'xp_modifier' => $k->getXpModifier(),
                'capacity' => $entry['capacity'],
                'player_count' => $entry['playerCount'],
            ];
        }, $kingdoms);

        return $this->json($data);
    }
}
