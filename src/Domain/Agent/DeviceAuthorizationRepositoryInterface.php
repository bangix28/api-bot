<?php

namespace App\Domain\Agent;

interface DeviceAuthorizationRepositoryInterface
{
    public function save(DeviceAuthorization $authorization): void;

    public function findByUserCode(UserCode $userCode): ?DeviceAuthorization;

    /** Recherche par le secret de l'agent : le dépôt compare des empreintes. */
    public function findByDeviceCode(string $deviceCode): ?DeviceAuthorization;
}
