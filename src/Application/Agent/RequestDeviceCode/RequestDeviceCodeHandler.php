<?php

namespace App\Application\Agent\RequestDeviceCode;

use App\Domain\Agent\DeviceAuthorization;
use App\Domain\Agent\DeviceAuthorizationRepositoryInterface;
use App\Domain\Agent\SecretGeneratorInterface;
use App\Domain\Agent\UserCode;
use App\Domain\Shared\ClockInterface;

/** L'agent demande un code d'appairage à afficher au joueur (contrat, § 1.1). */
final readonly class RequestDeviceCodeHandler
{
    public function __construct(
        private DeviceAuthorizationRepositoryInterface $authorizations,
        private SecretGeneratorInterface $secrets,
        private ClockInterface $clock,
    ) {
    }

    public function handle(): DeviceCodeView
    {
        // Le deviceCode en clair n'est remis qu'à l'agent : seule son empreinte est enregistrée.
        $deviceCode = $this->secrets->deviceCode();
        $authorization = DeviceAuthorization::request($deviceCode, $this->freeUserCode(), $this->clock->now());
        $this->authorizations->save($authorization);

        return new DeviceCodeView(
            $authorization->userCode->display(),
            $deviceCode,
            DeviceAuthorization::LIFETIME_SECONDS,
            DeviceAuthorization::POLL_INTERVAL_SECONDS,
        );
    }

    /**
     * Un userCode encore valide ne doit désigner qu'une demande : avec plus de
     * 800 milliards de codes possibles, une collision est rarissime, mais elle
     * ferait approuver l'agent d'un autre.
     */
    private function freeUserCode(): UserCode
    {
        do {
            $userCode = $this->secrets->userCode();
            $existing = $this->authorizations->findByUserCode($userCode);
        } while ($existing !== null && !$existing->isExpiredAt($this->clock->now()));

        return $userCode;
    }
}
