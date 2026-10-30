<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ListKingdomsCommand;
use App\Entity\Kingdom\Kingdom;
use App\Enum\PacePreset;
use App\Repository\Kingdom\KingdomRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[AllowMockObjectsWithoutExpectations]
class ListKingdomsCommandTest extends TestCase
{
    public function testListsKingdoms(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Main Kingdom');
        $kingdom->setTimezone('Europe/Prague');
        $kingdom->setLanguage('cs');
        $kingdom->setPacePreset(PacePreset::Classic);
        $idProperty = new \ReflectionProperty(Kingdom::class, 'id');
        $idProperty->setValue($kingdom, 1);

        $repository = $this->createMock(KingdomRepository::class);
        $repository->method('findBy')->with([], ['id' => 'ASC'])->willReturn([$kingdom]);

        $tester = new CommandTester(new ListKingdomsCommand($repository));
        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('Main Kingdom', $tester->getDisplay());
        $this->assertStringContainsString('classic', $tester->getDisplay());
    }
}
