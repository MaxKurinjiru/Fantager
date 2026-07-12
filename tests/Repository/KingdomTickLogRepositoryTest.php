<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Enum\TickType;
use App\Repository\Kingdom\KingdomTickLogRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KingdomTickLogRepositoryTest extends KernelTestCase
{
    private ?\Doctrine\ORM\EntityManagerInterface $em = null;
    private ?KingdomTickLogRepository $repository = null;
    private ?Kingdom $kingdom = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $this->em = $em;

        /** @var KingdomTickLogRepository $repository */
        $repository = $container->get(KingdomTickLogRepository::class);
        $this->repository = $repository;

        // Create a temporary kingdom
        $kingdom = new Kingdom();
        $kingdom->setName('Stale Test Kingdom');
        $kingdom->setLanguage('cs');
        $kingdom->setGameSpeed('1.0');
        $kingdom->setTimezone('UTC');
        $this->kingdom = $kingdom;

        $em->persist($kingdom);
        $em->flush();
    }

    protected function tearDown(): void
    {
        $em = $this->em;
        $repository = $this->repository;
        $kingdom = $this->kingdom;

        if ($em !== null && $repository !== null && $kingdom !== null) {
            $logs = $repository->findBy(['kingdom' => $kingdom]);
            foreach ($logs as $log) {
                $em->remove($log);
            }
            $em->remove($kingdom);
            $em->flush();
            $em->close();
        }

        $this->em = null;
        $this->repository = null;
        $this->kingdom = null;

        parent::tearDown();
        restore_exception_handler();
    }

    public function testRecoverStaleTicks(): void
    {
        $em = $this->em;
        $repository = $this->repository;
        $kingdom = $this->kingdom;

        $this->assertNotNull($em);
        $this->assertNotNull($repository);
        $this->assertNotNull($kingdom);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // 1. Stale tick (processing, executed 45s ago, retryCount = 0) -> should be retried (status pending, retryCount = 1)
        $log1 = new KingdomTickLog();
        $log1->setKingdom($kingdom);
        $log1->setTickType(TickType::WeeklyTraining);
        $log1->setScheduledAt($now->modify('-1 hour'));
        $log1->setStatus('processing');
        $log1->setExecutedAt($now->modify('-45 seconds'));
        $log1->setRetryCount(0);
        $em->persist($log1);

        // 2. Stale tick at max retries (dispatched, executed 45s ago, retryCount = 2) -> should fail (status failed, retryCount = 3)
        $log2 = new KingdomTickLog();
        $log2->setKingdom($kingdom);
        $log2->setTickType(TickType::LeagueMatch);
        $log2->setScheduledAt($now->modify('-1 hour'));
        $log2->setStatus('dispatched');
        $log2->setExecutedAt($now->modify('-45 seconds'));
        $log2->setRetryCount(2);
        $em->persist($log2);

        // 3. Fresh tick (processing, executed 5s ago, retryCount = 0) -> should remain unchanged
        $log3 = new KingdomTickLog();
        $log3->setKingdom($kingdom);
        $log3->setTickType(TickType::DailyReset);
        $log3->setScheduledAt($now->modify('-1 hour'));
        $log3->setStatus('processing');
        $log3->setExecutedAt($now->modify('-5 seconds'));
        $log3->setRetryCount(0);
        $em->persist($log3);

        // 4. Pending tick (pending, scheduled 45s ago) -> should remain unchanged
        $log4 = new KingdomTickLog();
        $log4->setKingdom($kingdom);
        $log4->setTickType(TickType::FatigueRecovery);
        $log4->setScheduledAt($now->modify('-1 hour'));
        $log4->setStatus('pending');
        $log4->setExecutedAt($now->modify('-45 seconds'));
        $log4->setRetryCount(0);
        $em->persist($log4);

        $em->flush();

        // Run recovery with a 30-second threshold
        $threshold = $now->modify('-30 seconds');
        $recoveredCount = $repository->recoverStaleTicks($kingdom, $threshold);

        $this->assertEquals(2, $recoveredCount);

        // Refresh from database and assert
        $em->refresh($log1);
        $em->refresh($log2);
        $em->refresh($log3);
        $em->refresh($log4);

        // Assert Log 1: recovered and pending
        $this->assertEquals('pending', $log1->getStatus());
        $this->assertEquals(1, $log1->getRetryCount());
        $this->assertStringContainsString('Worker timeout/crash detected. Auto-retry #1', (string) $log1->getErrorMessage());

        // Assert Log 2: failed because max retries reached
        $this->assertEquals('failed', $log2->getStatus());
        $this->assertEquals(3, $log2->getRetryCount());
        $this->assertStringContainsString('Worker timeout/crash detected. Maximum retries reached (3)', (string) $log2->getErrorMessage());

        // Assert Log 3: untouched
        $this->assertEquals('processing', $log3->getStatus());
        $this->assertEquals(0, $log3->getRetryCount());

        // Assert Log 4: untouched
        $this->assertEquals('pending', $log4->getStatus());
        $this->assertEquals(0, $log4->getRetryCount());
    }
}
