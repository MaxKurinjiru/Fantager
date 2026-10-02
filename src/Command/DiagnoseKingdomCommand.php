<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Kingdom\Kingdom;
use App\Repository\Kingdom\KingdomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:kingdom:diagnose',
    description: 'Diagnose the simulation and economic state of a kingdom.',
)]
class DiagnoseKingdomCommand extends Command
{
    public function __construct(
        private readonly KingdomRepository $kingdomRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('kingdom-id', InputArgument::OPTIONAL, 'Kingdom ID to diagnose')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Diagnose all kingdoms');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $kingdomId = $input->getArgument('kingdom-id');
        $all = $input->getOption('all');

        if ($all) {
            $kingdoms = $this->kingdomRepository->findAll();
        } elseif (null !== $kingdomId) {
            $kingdom = $this->kingdomRepository->find((int) $kingdomId);
            if (null === $kingdom) {
                $io->error(sprintf('Kingdom with ID %s not found.', $kingdomId));

                return Command::FAILURE;
            }
            $kingdoms = [$kingdom];
        } else {
            $allKingdoms = $this->kingdomRepository->findAll();
            if (empty($allKingdoms)) {
                $io->warning('No kingdoms exist in the database.');

                return Command::SUCCESS;
            }

            if (\count($allKingdoms) > 1) {
                $choices = [];
                foreach ($allKingdoms as $k) {
                    $kId = $k->getId();
                    if (null !== $kId) {
                        $choices[$kId] = sprintf('%s (ID: %d)', $k->getName(), $kId);
                    }
                }
                $selected = $io->choice('Select a kingdom to diagnose:', $choices);
                $selectedId = array_search($selected, $choices, true);
                $selectedKingdom = false !== $selectedId ? $this->kingdomRepository->find((int) $selectedId) : null;
                $kingdoms = null !== $selectedKingdom ? [$selectedKingdom] : $allKingdoms;
            } else {
                $kingdoms = $allKingdoms;
            }
        }

        foreach ($kingdoms as $kingdom) {
            $this->diagnoseKingdom($kingdom, $io);
        }

        return Command::SUCCESS;
    }

    private function diagnoseKingdom(Kingdom $kingdom, SymfonyStyle $io): void
    {
        $io->title(sprintf('Diagnosis for Kingdom: %s (ID: %d)', $kingdom->getName(), $kingdom->getId()));

        $io->definitionList(
            ['Timezone' => $kingdom->getTimezone()],
            ['Game Speed' => $kingdom->getGameSpeed()],
            ['Language' => $kingdom->getLanguage()],
            ['Royal Treasury Gold' => $kingdom->getRoyalTreasuryGold().' gold'],
            ['Season Length' => $kingdom->getSeasonLength().' days']
        );

        $this->diagnoseTicks($kingdom, $io);
        $this->diagnoseEconomy($kingdom, $io);
        $this->diagnoseRosters($kingdom, $io);
        $this->diagnoseMarketplace($kingdom, $io);
        $this->diagnoseCombat($kingdom, $io);

        $io->newLine();
        $io->writeln(str_repeat('-', 80));
    }

    private function diagnoseTicks(Kingdom $kingdom, SymfonyStyle $io): void
    {
        $io->section('1. Tick Pipeline & Infrastructure Health');

        $conn = $this->em->getConnection();
        $sql = '
            SELECT tick_type, status, COUNT(*) as cnt
            FROM kingdom_tick_log
            WHERE kingdom_id = :kingdomId
            GROUP BY tick_type, status
        ';

        $stmt = $conn->executeQuery($sql, ['kingdomId' => $kingdom->getId()]);
        $rows = $stmt->fetchAllAssociative();

        if (empty($rows)) {
            $io->note('No ticks have been scheduled/executed in this kingdom.');

            return;
        }

        // Organize counts
        $ticks = [];
        $statuses = ['pending', 'dispatched', 'processing', 'completed', 'failed'];
        foreach ($rows as $row) {
            $type = $row['tick_type'];
            if (!isset($ticks[$type])) {
                $ticks[$type] = array_fill_keys($statuses, 0);
            }
            $ticks[$type][$row['status']] = (int) $row['cnt'];
        }

        $table = new Table($io);
        $table->setHeaders(['Tick Type', 'Pending', 'Dispatched', 'Processing', 'Completed', 'Failed', 'Total']);

        foreach ($ticks as $type => $statusCounts) {
            $total = array_sum($statusCounts);
            $table->addRow([
                $type,
                $statusCounts['pending'],
                $statusCounts['dispatched'],
                $statusCounts['processing'],
                $statusCounts['completed'],
                $statusCounts['failed'],
                $total,
            ]);
        }
        $table->render();

        // Highlight failed ticks
        $failedSql = '
            SELECT id, tick_type, scheduled_at, executed_at, error_message
            FROM kingdom_tick_log
            WHERE kingdom_id = :kingdomId AND status = \'failed\'
            ORDER BY scheduled_at ASC
        ';
        $failedRows = $conn->executeQuery($failedSql, ['kingdomId' => $kingdom->getId()])->fetchAllAssociative();

        if (\count($failedRows) > 0) {
            $io->error(sprintf('Found %d failed tick(s) in this kingdom!', \count($failedRows)));
            foreach ($failedRows as $failed) {
                $io->writeln(sprintf('<fg=red>Failed Tick ID: %d</>', $failed['id']));
                $io->writeln(sprintf('  Type: %s', $failed['tick_type']));
                $io->writeln(sprintf('  Scheduled At: %s', $failed['scheduled_at']));
                $io->writeln(sprintf('  Executed At: %s', $failed['executed_at']));
                $io->writeln(sprintf('  Error Message: %s', $failed['error_message']));
                $io->newLine();
            }
        } else {
            $io->success('No failed ticks detected.');
        }
    }

    private function diagnoseEconomy(Kingdom $kingdom, SymfonyStyle $io): void
    {
        $io->section('2. Economic Stability & Wealth Distribution');

        $conn = $this->em->getConnection();
        $sql = '
            SELECT 
                COUNT(id) as total_teams,
                SUM(is_npc) as npc_teams,
                SUM(CASE WHEN is_npc = 0 THEN 1 ELSE 0 END) as player_teams,
                SUM(gold) as total_circulating_gold,
                AVG(gold) as avg_gold,
                MIN(gold) as min_gold,
                MAX(gold) as max_gold,
                SUM(unpaid_debt) as total_debt,
                AVG(unpaid_debt) as avg_debt,
                MAX(unpaid_debt) as max_debt,
                SUM(CASE WHEN crisis_weeks > 0 THEN 1 ELSE 0 END) as teams_in_crisis
            FROM team
            WHERE kingdom_id = :kingdomId
        ';

        $row = $conn->executeQuery($sql, ['kingdomId' => $kingdom->getId()])->fetchAssociative();

        if (empty($row) || 0 === (int) $row['total_teams']) {
            $io->warning('No teams found in this kingdom.');

            return;
        }

        $table = new Table($io);
        $table->setHeaders(['Metric', 'Value']);
        $table->addRow(['Total Teams', $row['total_teams'].' ('.$row['npc_teams'].' NPCs / '.$row['player_teams'].' Players)']);
        $table->addRow(['Total Gold in Circulation', $row['total_circulating_gold'].' gold']);
        $table->addRow(['Average / Min / Max Gold per Team', sprintf('%.2f / %d / %d gold', $row['avg_gold'], $row['min_gold'], $row['max_gold'])]);
        $table->addRow(['Total Outstanding Debt', $row['total_debt'].' gold']);
        $table->addRow(['Average / Max Debt per Team', sprintf('%.2f / %d gold', $row['avg_debt'], $row['max_debt'])]);
        $table->addRow(['Teams in Financial Crisis', $row['teams_in_crisis']]);
        $table->render();

        // Transaction types gold flow audit
        $txSql = '
            SELECT fr.type, SUM(fr.gold_change) as total_gold_flow, COUNT(fr.id) as tx_count
            FROM team_financial_record fr
            JOIN team t ON fr.team_id = t.id
            WHERE t.kingdom_id = :kingdomId
            GROUP BY fr.type
            ORDER BY total_gold_flow DESC
        ';
        $txRows = $conn->executeQuery($txSql, ['kingdomId' => $kingdom->getId()])->fetchAllAssociative();

        if (\count($txRows) > 0) {
            $io->text('Gold flows by transaction type:');
            $txTable = new Table($io);
            $txTable->setHeaders(['Transaction Type', 'Net Gold Flow', 'Count']);
            foreach ($txRows as $txRow) {
                $flowStr = $txRow['total_gold_flow'] > 0 ? ('+'.$txRow['total_gold_flow']) : $txRow['total_gold_flow'];
                $txTable->addRow([$txRow['type'], $flowStr.' gold', $txRow['tx_count']]);
            }
            $txTable->render();
        }
    }

    private function diagnoseRosters(Kingdom $kingdom, SymfonyStyle $io): void
    {
        $io->section('3. Roster Management & Hero Progression');

        $conn = $this->em->getConnection();
        $sql = '
            SELECT 
                t.id as team_id, 
                t.name as team_name, 
                t.is_npc as is_npc,
                COUNT(CASE WHEN h.role = \'combatant\' THEN 1 END) as combatants_count,
                COUNT(CASE WHEN h.role = \'trainer\' THEN 1 END) as trainers_count,
                COUNT(h.id) as total_roster
            FROM team t
            LEFT JOIN hero h ON h.team_id = t.id AND h.status NOT IN (\'dead\', \'retired\')
            WHERE t.kingdom_id = :kingdomId
            GROUP BY t.id, t.name, t.is_npc
            ORDER BY combatants_count ASC
        ';

        $rows = $conn->executeQuery($sql, ['kingdomId' => $kingdom->getId()])->fetchAllAssociative();

        if (empty($rows)) {
            $io->warning('No team rosters detected.');

            return;
        }

        $table = new Table($io);
        $table->setHeaders(['Team ID', 'Team Name', 'Type', 'Combatants', 'Trainers', 'Total Active']);

        $understaffedTeams = [];
        foreach ($rows as $row) {
            $isNpc = 1 === (int) $row['is_npc'] ? 'NPC' : 'Player';
            $combatants = (int) $row['combatants_count'];
            $combatantsStr = $combatants;

            if ($combatants < 6) {
                $combatantsStr = sprintf('<fg=red;options=bold>%d</>', $combatants);
                $understaffedTeams[] = $row['team_name'].' (ID: '.$row['team_id'].', Combatants: '.$combatants.')';
            }

            $table->addRow([
                $row['team_id'],
                $row['team_name'],
                $isNpc,
                $combatantsStr,
                $row['trainers_count'],
                $row['total_roster'],
            ]);
        }
        $table->render();

        if (!empty($understaffedTeams)) {
            $io->warning(sprintf(
                "CRITICAL: Found %d team(s) with less than 6 combat-ready heroes!\nThese teams will automatically forfeit league matches.",
                \count($understaffedTeams)
            ));
            foreach ($understaffedTeams as $ut) {
                $io->writeln('  - '.$ut);
            }
        } else {
            $io->success('All active teams have at least 6 combat-ready heroes.');
        }

        // Progression stats
        $progSql = '
            SELECT 
                role,
                COUNT(*) as count,
                AVG(level) as avg_level,
                AVG(str) as avg_str,
                AVG(dex) as avg_dex,
                AVG(kon) as avg_kon,
                AVG(spd) as avg_spd,
                AVG(intel) as avg_intel,
                AVG(wil) as avg_wil
            FROM hero
            WHERE status NOT IN (\'dead\', \'retired\') AND team_id IN (
                SELECT id FROM team WHERE kingdom_id = :kingdomId
            )
            GROUP BY role
        ';
        $progRows = $conn->executeQuery($progSql, ['kingdomId' => $kingdom->getId()])->fetchAllAssociative();
        if (\count($progRows) > 0) {
            $io->text('Hero Attribute Averages:');
            $progTable = new Table($io);
            $progTable->setHeaders(['Role', 'Count', 'Avg Lvl', 'Avg STR', 'Avg DEX', 'Avg KON', 'Avg SPD', 'Avg INT', 'Avg WIL']);
            foreach ($progRows as $pRow) {
                $progTable->addRow([
                    $pRow['role'],
                    $pRow['count'],
                    sprintf('%.1f', $pRow['avg_level']),
                    sprintf('%.1f', $pRow['avg_str']),
                    sprintf('%.1f', $pRow['avg_dex']),
                    sprintf('%.1f', $pRow['avg_kon']),
                    sprintf('%.1f', $pRow['avg_spd']),
                    sprintf('%.1f', $pRow['avg_intel']),
                    sprintf('%.1f', $pRow['avg_wil']),
                ]);
            }
            $progTable->render();
        }
    }

    private function diagnoseMarketplace(Kingdom $kingdom, SymfonyStyle $io): void
    {
        $io->section('4. Marketplace Liquidity & Valuation');

        $conn = $this->em->getConnection();
        $sql = '
            SELECT listing_type, status, COUNT(*) as cnt, AVG(price_gold) as avg_price
            FROM marketplace_listing
            WHERE kingdom_id = :kingdomId
            GROUP BY listing_type, status
            ORDER BY listing_type, status
        ';

        $rows = $conn->executeQuery($sql, ['kingdomId' => $kingdom->getId()])->fetchAllAssociative();

        if (empty($rows)) {
            $io->note('No marketplace listings detected.');

            return;
        }

        $table = new Table($io);
        $table->setHeaders(['Listing Type', 'Status', 'Count', 'Average Price']);
        foreach ($rows as $row) {
            $table->addRow([
                $row['listing_type'],
                $row['status'],
                $row['cnt'],
                sprintf('%.2f gold', $row['avg_price']),
            ]);
        }
        $table->render();
    }

    private function diagnoseCombat(Kingdom $kingdom, SymfonyStyle $io): void
    {
        $io->section('5. League Play & Combat Outcomes');

        $conn = $this->em->getConnection();
        $sql = '
            SELECT result, score_a, score_b, combat_log
            FROM combat_battle
            WHERE kingdom_id = :kingdomId
        ';

        $rows = $conn->executeQuery($sql, ['kingdomId' => $kingdom->getId()])->fetchAllAssociative();

        if (empty($rows)) {
            $io->note('No battles have been recorded in this kingdom.');

            return;
        }

        $totalBattles = \count($rows);
        $homeWins = 0;
        $awayWins = 0;
        $draws = 0;
        $forfeitBattles = 0;
        $totalScores = 0;

        foreach ($rows as $row) {
            $result = $row['result'];
            if ('win_a' === $result) {
                ++$homeWins;
            } elseif ('win_b' === $result) {
                ++$awayWins;
            } else {
                ++$draws;
            }

            $combatLogStr = $row['combat_log'];
            $isForfeit = false;
            if (null !== $combatLogStr && '' !== $combatLogStr) {
                $log = json_decode($combatLogStr, true);
                if (is_array($log) && isset($log['simulator']) && 'forfeit' === $log['simulator']) {
                    $isForfeit = true;
                }
            }

            if ($isForfeit) {
                ++$forfeitBattles;
            }

            $totalScores += (int) $row['score_a'] + (int) $row['score_b'];
        }

        $table = new Table($io);
        $table->setHeaders(['Metric', 'Value']);
        $table->addRow(['Total Battles Simulated', $totalBattles]);
        $table->addRow(['Home Wins / Away Wins / Draws', sprintf('%d / %d / %d', $homeWins, $awayWins, $draws)]);
        $table->addRow(['Forfeit Matches (Roster Starvation)', sprintf('%d (%.1f%%)', $forfeitBattles, ($forfeitBattles / $totalBattles) * 100)]);
        $table->addRow(['Average Score (Kills) per Match', sprintf('%.2f', $totalScores / $totalBattles)]);
        $table->render();
    }
}
