<?php

namespace App\Infrastructure\Player;

use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\RiotId;
use App\Domain\Player\TrackedRiotAccount;
use App\Domain\Player\TrackedRiotAccountRepositoryInterface;
use App\Entity\RiotAccount;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTrackedRiotAccountRepository implements TrackedRiotAccountRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findByRiotId(RiotId $riotId): ?TrackedRiotAccount
    {
        // LOWER explicite plutôt que de compter sur la collation de la colonne.
        $row = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(RiotAccount::class, 'a')
            ->where('LOWER(a.riotId) = LOWER(:riotId)')
            // Un compte sans puuid n'a jamais été rafraîchi : rien ne permettrait
            // de le suivre en partie, il n'est pas encore liable.
            ->andWhere("a.puuid IS NOT NULL AND a.puuid <> ''")
            ->setParameter('riotId', $riotId->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $row === null ? null : self::toDomain($row);
    }

    /** @return TrackedRiotAccount[] */
    public function findOwnedBy(string $discordId): array
    {
        $rows = $this->entityManager->getRepository(RiotAccount::class)
            ->findBy(['discordId' => $discordId], ['riotId' => 'ASC']);

        return array_map(self::toDomain(...), $rows);
    }

    public function linkTo(string $puuid, DiscordIdentity $player): void
    {
        $row = $this->entityManager->getRepository(RiotAccount::class)->findOneBy(['puuid' => $puuid])
            ?? throw new \LogicException(sprintf('Aucun compte suivi pour le puuid %s.', $puuid));

        $row->setDiscordId($player->userId)->setDiscordUsername($player->username);
        $this->entityManager->flush();
    }

    private static function toDomain(RiotAccount $row): TrackedRiotAccount
    {
        return new TrackedRiotAccount($row->getRiotId(), $row->getPuuid(), $row->getDiscordId());
    }
}
