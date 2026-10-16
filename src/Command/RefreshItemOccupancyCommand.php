<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Item\ItemService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:item-occupancy:refresh',
    description: 'Unequip off-hand items that sit next to a two-handed main-hand weapon (no chronicle).',
)]
class RefreshItemOccupancyCommand extends Command
{
    public function __construct(
        private readonly ItemService $itemService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cleared = $this->itemService->clearIllegalTwoHandOccupancy();
        $io->success(sprintf('Cleared %d illegal off-hand occupancy row(s).', $cleared));

        return Command::SUCCESS;
    }
}
