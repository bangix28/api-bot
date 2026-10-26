<?php

namespace App\Tests\Domain\Agent;

use App\Domain\Agent\DeviceAuthorization;
use App\Domain\Agent\DeviceAuthorizationRepositoryInterface;
use App\Domain\Agent\SecretDigest;
use App\Domain\Agent\UserCode;

final class InMemoryDeviceAuthorizationRepository implements DeviceAuthorizationRepositoryInterface
{
    /** @var array<string, DeviceAuthorization> indexées par empreinte du deviceCode */
    public array $authorizations = [];

    public function save(DeviceAuthorization $authorization): void
    {
        $this->authorizations[$authorization->deviceCodeDigest] = $authorization;
    }

    public function findByUserCode(UserCode $userCode): ?DeviceAuthorization
    {
        $matching = array_filter(
            $this->authorizations,
            static fn(DeviceAuthorization $authorization) => $authorization->userCode->value === $userCode->value,
        );
        usort($matching, static fn(DeviceAuthorization $left, DeviceAuthorization $right) => $right->expiresAt <=> $left->expiresAt);

        return $matching[0] ?? null;
    }

    public function findByDeviceCode(string $deviceCode): ?DeviceAuthorization
    {
        return $this->authorizations[SecretDigest::of($deviceCode)] ?? null;
    }
}
