<?php

namespace App\Infrastructure\Player;

use App\Domain\Agent\SecretDigest;
use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\PlayerSession;
use App\Domain\Player\PlayerSessionRepositoryInterface;
use App\Domain\Shared\ClockInterface;
use App\Entity\PlayerRefreshToken;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePlayerSessionRepository implements PlayerSessionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function save(PlayerSession $session): void
    {
        $row = $this->entityManager->getRepository(PlayerRefreshToken::class)->findOneBy(['digest' => $session->digest]);

        if ($row !== null) {
            $row->expireAt($session->expiresAt);
        } else {
            // Chaque rafraîchissement crée une ligne : on balaie au passage les
            // sessions expirées du joueur, plutôt que de les laisser s'accumuler.
            $this->entityManager->createQueryBuilder()
                ->delete(PlayerRefreshToken::class, 'token')
                ->where('token.discordId = :discordId')
                ->andWhere('token.expiresAt < :now')
                ->setParameter('discordId', $session->player->userId)
                ->setParameter('now', $this->clock->now())
                ->getQuery()
                ->execute();

            $this->entityManager->persist(new PlayerRefreshToken(
                $session->digest,
                $session->player->userId,
                $session->player->username,
                $session->expiresAt,
            ));
        }

        $this->entityManager->flush();
    }

    public function findByToken(string $plainToken): ?PlayerSession
    {
        $row = $this->entityManager->getRepository(PlayerRefreshToken::class)
            ->findOneBy(['digest' => SecretDigest::of($plainToken)]);

        return $row === null ? null : new PlayerSession(
            $row->getDigest(),
            new DiscordIdentity($row->getDiscordId(), $row->getDiscordUsername()),
            $row->getExpiresAt(),
        );
    }

    public function delete(PlayerSession $session): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(PlayerRefreshToken::class, 'token')
            ->where('token.digest = :digest')
            ->setParameter('digest', $session->digest)
            ->getQuery()
            ->execute();
    }
}
