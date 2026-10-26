<?php

namespace App\Infrastructure\LanChallenge;

use App\Domain\LanChallenge\CardOffer;
use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\PlayerRunRepositoryInterface;
use App\Domain\LanChallenge\RoundResult;
use App\Domain\Shared\ClockInterface;
use App\Entity\LanEvent;
use App\Entity\LanPlayerRun;
use App\Entity\LanRoundResult;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePlayerRunRepository implements PlayerRunRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function find(int $lanId, string $puuid): ?PlayerRun
    {
        $row = $this->findRow($lanId, $puuid);

        return $row === null ? null : self::toDomain($lanId, $row);
    }

    /** @return PlayerRun[] */
    public function findByLan(int $lanId): array
    {
        $rows = $this->entityManager->getRepository(LanPlayerRun::class)->findBy(['lanEvent' => $lanId]);

        return array_map(static fn(LanPlayerRun $row) => self::toDomain($lanId, $row), $rows);
    }

    /**
     * L'historique d'un parcours ne fait que s'allonger : seules les manches
     * absentes de la base sont ajoutées, horodatées au moment de leur règlement.
     */
    public function save(PlayerRun $run): void
    {
        $row = $this->findRow($run->lanId, $run->puuid);
        if ($row === null) {
            $row = new LanPlayerRun($this->entityManager->getReference(LanEvent::class, $run->lanId), $run->puuid);
            $this->entityManager->persist($row);
        }

        $row->setRerollsLeft($run->rerollsLeft)
            ->setScore($run->score)
            ->setOffer($run->offer === null ? null : array_map(static fn(Challenge $card) => $card->value, $run->offer->cards))
            ->setActiveCard($run->activeCard)
            ->setLockedGameId($run->lockedGameId);

        $storedRounds = $row->getResults()->count();
        foreach (array_slice($run->results, $storedRounds, preserve_keys: true) as $position => $result) {
            $row->addResult(new LanRoundResult(
                $row, $position, $result->gameId, $result->card, $result->verdict,
                $result->points, $result->roundBonus, $this->clock->now(),
            ));
        }

        $this->entityManager->flush();
    }

    private function findRow(int $lanId, string $puuid): ?LanPlayerRun
    {
        return $this->entityManager->getRepository(LanPlayerRun::class)
            ->findOneBy(['lanEvent' => $lanId, 'puuid' => $puuid]);
    }

    private static function toDomain(int $lanId, LanPlayerRun $row): PlayerRun
    {
        $offer = $row->getOffer() === null
            ? null
            : new CardOffer(array_map(static fn(string $code) => Challenge::from($code), $row->getOffer()));

        return PlayerRun::restore(
            $lanId,
            $row->getPuuid(),
            $row->getRerollsLeft(),
            $row->getScore(),
            $offer,
            $row->getActiveCard(),
            $row->getLockedGameId(),
            array_map(
                static fn(LanRoundResult $result) => new RoundResult(
                    $result->getGameId(), $result->getCard(), $result->getVerdict(), $result->getPoints(), $result->isRoundBonus(),
                ),
                $row->getResults()->toArray(),
            ),
        );
    }
}
