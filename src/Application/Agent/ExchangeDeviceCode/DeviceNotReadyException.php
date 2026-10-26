<?php

namespace App\Application\Agent\ExchangeDeviceCode;

use App\Domain\Agent\DevicePollStatus;

/** L'appairage n'est pas (encore) approuvé : l'agent doit réessayer ou recommencer. */
final class DeviceNotReadyException extends \DomainException
{
    public function __construct(public readonly DevicePollStatus $status)
    {
        parent::__construct(match ($status) {
            DevicePollStatus::PENDING => 'Le code n\'a pas encore été saisi sur le site.',
            DevicePollStatus::SLOW_DOWN => 'Interrogation trop rapprochée : espace les demandes.',
            DevicePollStatus::EXPIRED => 'Le code a expiré sans être saisi : demandes-en un nouveau.',
            DevicePollStatus::APPROVED => throw new \LogicException('Un appairage approuvé est prêt.'),
        });
    }
}
