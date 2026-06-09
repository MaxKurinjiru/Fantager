<?php

declare(strict_types=1);

namespace App\Service\Hero;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Repository\Hero\HeroRepository;
use Doctrine\ORM\EntityManagerInterface;

class HeroService
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return list<Hero> */
    public function listByTeam(Team $team): array
    {
        return $this->heroRepository->findBy(['team' => $team], ['id' => 'ASC']);
    }

    public function findForTeam(int $id, Team $team): ?Hero
    {
        return $this->heroRepository->findOneBy(['id' => $id, 'team' => $team]);
    }

    public function rename(Hero $hero, string $name): void
    {
        $name = trim($name);
        if ('' === $name) {
            throw new \InvalidArgumentException('Hero name cannot be empty.');
        }

        if (mb_strlen($name) > 100) {
            throw new \InvalidArgumentException('Hero name must not exceed 100 characters.');
        }

        $hero->setName($name);
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    public function serialize(Hero $hero): array
    {
        return [
            'id' => $hero->getId(),
            'name' => $hero->getName(),
            'race' => $hero->getRace()->value,
            'level' => $hero->getLevel(),
            'xp' => $hero->getXp(),
            'age' => $hero->getAge(),
            'status' => $hero->getStatus()->value,
            'form' => $hero->getForm(),
            'fatigue' => $hero->getFatigue(),
            'morale' => $hero->getMorale(),
            'magic_capacity' => $hero->getMagicCapacity(),
            'attributes' => [
                'str' => $hero->getStr(),
                'dex' => $hero->getDex(),
                'kon' => $hero->getKon(),
                'spd' => $hero->getSpd(),
                'int' => $hero->getIntel(),
                'wil' => $hero->getWil(),
                'cha' => $hero->getCha(),
                'lck' => $hero->getLck(),
            ],
        ];
    }
}
