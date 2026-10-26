<?php

namespace App\Infrastructure\Agent;

use App\Domain\Agent\DeviceApproval;
use App\Domain\Agent\DeviceAuthorization;
use App\Domain\Agent\DeviceAuthorizationRepositoryInterface;
use App\Domain\Agent\SecretDigest;
use App\Domain\Agent\UserCode;
use App\Domain\Player\DiscordIdentity;
use App\Entity\AgentDeviceAuthorization;
use App\Entity\LanEvent;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineDeviceAuthorizationRepository implements DeviceAuthorizationRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(DeviceAuthorization $authorization): void
    {
        $row = $this->findRow($authorization->deviceCodeDigest);
        if ($row === null) {
            $row = new AgentDeviceAuthorization($authorization->deviceCodeDigest);
            $this->entityManager->persist($row);
        }

        $approval = $authorization->approval;
        $row->setUserCode($authorization->userCode->value)
            ->setExpiresAt($authorization->expiresAt)
            ->setLastPolledAt($authorization->lastPolledAt)
            ->setApproval(
                $approval?->player->userId,
                $approval?->player->username,
                $approval === null ? null : $this->entityManager->getReference(LanEvent::class, $approval->lanId),
            )
            ->setConsumed($authorization->consumed);

        $this->entityManager->flush();
    }

    public function findByUserCode(UserCode $userCode): ?DeviceAuthorization
    {
        // Un même code a pu resservir après expiration : la demande la plus récente fait foi.
        $row = $this->entityManager->getRepository(AgentDeviceAuthorization::class)
            ->findOneBy(['userCode' => $userCode->value], ['expiresAt' => 'DESC']);

        return $row === null ? null : self::toDomain($row);
    }

    public function findByDeviceCode(string $deviceCode): ?DeviceAuthorization
    {
        $row = $this->findRow(SecretDigest::of($deviceCode));

        return $row === null ? null : self::toDomain($row);
    }

    private function findRow(string $deviceCodeDigest): ?AgentDeviceAuthorization
    {
        return $this->entityManager->getRepository(AgentDeviceAuthorization::class)
            ->findOneBy(['deviceCodeDigest' => $deviceCodeDigest]);
    }

    private static function toDomain(AgentDeviceAuthorization $row): DeviceAuthorization
    {
        $approval = $row->getApprovedDiscordId() === null || $row->getApprovedLanEvent() === null
            ? null
            : new DeviceApproval(
                new DiscordIdentity($row->getApprovedDiscordId(), $row->getApprovedDiscordUsername()),
                $row->getApprovedLanEvent()->getId(),
            );

        return DeviceAuthorization::restore(
            $row->getDeviceCodeDigest(),
            new UserCode($row->getUserCode()),
            $row->getExpiresAt(),
            $row->getLastPolledAt(),
            $approval,
            $row->isConsumed(),
        );
    }
}
