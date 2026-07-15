<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Spell\SpellService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:spells:seed',
    description: 'Seed the database with basic spells for Tiers 1 and 2 across all magic schools',
)]
class SeedSpellsCommand extends Command
{
    public function __construct(
        private readonly SpellService $spellService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->spellService->seedDefaultSpells();
            $io->success(sprintf(
                'Spells seeding completed: %d new spells added, %d skipped.',
                $result['inserted'],
                $result['skipped']
            ));
        } catch (\Exception $e) {
            $io->error(sprintf('Error seeding spells: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
