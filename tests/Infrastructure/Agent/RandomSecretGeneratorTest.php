<?php

namespace App\Tests\Infrastructure\Agent;

use App\Domain\Agent\UserCode;
use App\Infrastructure\Agent\RandomSecretGenerator;
use PHPUnit\Framework\TestCase;

class RandomSecretGeneratorTest extends TestCase
{
    public function testLesSecretsOntLeurPrefixeEtAssezDEntropie(): void
    {
        $generator = new RandomSecretGenerator();

        $this->assertMatchesRegularExpression('/^dev_[A-Za-z0-9_-]{43}$/', $generator->deviceCode());
        $this->assertMatchesRegularExpression('/^agt_[A-Za-z0-9_-]{43}$/', $generator->agentToken());
    }

    public function testLesCodesDAppairageSontValidesEtVaries(): void
    {
        $generator = new RandomSecretGenerator();

        $userCodes = [];
        for ($draw = 0; $draw < 200; $draw++) {
            $userCodes[] = $generator->userCode()->value;
        }

        $this->assertSame(UserCode::LENGTH, strlen($userCodes[0]));
        $this->assertGreaterThan(195, count(array_unique($userCodes)), 'Des codes tirés au hasard ne se répètent pratiquement jamais.');
    }

    public function testDeuxJetonsNeSeRessemblentJamais(): void
    {
        $generator = new RandomSecretGenerator();

        $this->assertNotSame($generator->agentToken(), $generator->agentToken());
    }
}
