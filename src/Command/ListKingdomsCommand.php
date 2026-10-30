<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Kingdom\KingdomRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:kingdom:list',
    description: 'List kingdoms (id, name, pace, timezone).',
)]
class ListKingdomsCommand extends Command
{
    public function __construct(
        private readonly KingdomRepository $kingdomRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $kingdoms = $this->kingdomRepository->findBy([], ['id' => 'ASC']);
        if ([] === $kingdoms) {
            $io->warning('No kingdoms found.');

            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Name', 'Pace', 'Timezone', 'Language'],
            array_map(static fn ($kingdom): array => [
                (string) $kingdom->getId(),
                $kingdom->getName(),
                $kingdom->getPacePreset()->value,
                $kingdom->getTimezone(),
                $kingdom->getLanguage(),
            ], $kingdoms),
        );

        return Command::SUCCESS;
    }
}
