<?php

declare(strict_types=1);

namespace App\Service\Economy;

use App\Entity\Team\FinancialRecord;
use App\Entity\Team\Team;
use App\Repository\Team\FinancialRecordRepository;

class FinanceSummaryService
{
    private const PERIOD_DAYS = 7;

    public function __construct(
        private readonly FinancialRecordRepository $recordRepository,
        private readonly FinancialCrisisService $financialCrisisService,
    ) {
    }

    /**
     * @return array{
     *     crisis: array<string, mixed>,
     *     period_days: int,
     *     period: array{income: int, expense: int, net: int, transaction_count: int},
     *     all_time: array{income: int, expense: int, net: int, transaction_count: int},
     *     recent_transactions: list<array{type: string, gold_change: int, created_at: \DateTimeImmutable}>
     * }
     */
    public function buildOverview(Team $team): array
    {
        $since = new \DateTimeImmutable(
            sprintf('-%d days', self::PERIOD_DAYS),
            new \DateTimeZone('UTC')
        );

        return [
            'crisis' => $this->financialCrisisService->getStatus($team),
            'period_days' => self::PERIOD_DAYS,
            'period' => $this->recordRepository->getGoldSummarySince($team, $since),
            'all_time' => $this->recordRepository->getGoldSummarySince($team),
            'recent_transactions' => array_map(
                static fn (FinancialRecord $record): array => [
                    'type' => $record->getType()->value,
                    'gold_change' => $record->getGoldChange(),
                    'created_at' => $record->getCreatedAt(),
                ],
                $this->recordRepository->findRecentByTeam($team, 5),
            ),
        ];
    }
}
