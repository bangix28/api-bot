<?php

namespace App\Tests\Application\Player\SyncRiotIds;

use App\Application\Player\SyncRiotIds\SyncRiotIdsHandler;
use App\Application\Player\SyncRiotIds\SyncRiotIdsReport;
use App\Domain\Player\RiotId;
use App\Domain\Player\TrackedRiotAccount;
use App\Tests\Domain\Logging\SpyLogger;
use App\Tests\Domain\Player\InMemoryTrackedRiotAccountRepository;
use App\Tests\Domain\Player\ScriptedRiotIdDirectory;
use PHPUnit\Framework\TestCase;

class SyncRiotIdsHandlerTest extends TestCase
{
    private InMemoryTrackedRiotAccountRepository $accounts;
    private ScriptedRiotIdDirectory $directory;
    private SpyLogger $logger;

    protected function setUp(): void
    {
        $this->accounts = new InMemoryTrackedRiotAccountRepository();
        $this->directory = new ScriptedRiotIdDirectory();
        $this->logger = new SpyLogger();
    }

    private function sync(): SyncRiotIdsReport
    {
        return (new SyncRiotIdsHandler($this->accounts, $this->directory, $this->logger))->handle();
    }

    private function riotIdOf(string $puuid): string
    {
        foreach ($this->accounts->findAllTracked() as $account) {
            if ($account->puuid === $puuid) {
                return $account->riotId;
            }
        }

        throw new \LogicException('Compte introuvable : ' . $puuid);
    }

    public function testUnIdentifiantTechniqueEstRemplaceParLeRiotIdLisible(): void
    {
        // Cas réel de la prod : le champ contenait l'ancien identifiant « summoner ».
        $this->accounts->add(new TrackedRiotAccount('u0CQf9D5pRi9dhB1tTK-syPYVZ8BD-0bFUWC12ohGM7CVhc', 'puuid-shoteur', null));
        $this->directory->knows('puuid-shoteur', 'BCTG sHoTeUr#4861');

        $report = $this->sync();

        $this->assertSame('BCTG sHoTeUr#4861', $this->riotIdOf('puuid-shoteur'));
        $this->assertSame([['from' => 'u0CQf9D5pRi9dhB1tTK-syPYVZ8BD-0bFUWC12ohGM7CVhc', 'to' => 'BCTG sHoTeUr#4861']], $report->renamed);
        $this->assertSame(0, $report->unchanged);
        $this->assertSame([], $report->failed);
        $this->assertNotNull($this->accounts->findByRiotId(new RiotId('bctg shoteur#4861')), 'Le compte est désormais liable sous son Riot ID.');
    }

    public function testUnChangementDePseudoEstSuivi(): void
    {
        $this->accounts->add(new TrackedRiotAccount('Ancien#EUW', 'puuid-a', '1001'));
        $this->directory->knows('puuid-a', 'Nouveau#EUW');

        $this->sync();

        $this->assertSame('Nouveau#EUW', $this->riotIdOf('puuid-a'));
        $this->assertSame(['Nouveau#EUW'], array_map(static fn(TrackedRiotAccount $account) => $account->riotId, $this->accounts->findOwnedBy('1001')), 'La liaison au joueur est conservée.');
    }

    public function testUnSimpleChangementDeCasseEstRepris(): void
    {
        $this->accounts->add(new TrackedRiotAccount('pseudo#euw', 'puuid-a', null));
        $this->directory->knows('puuid-a', 'Pseudo#EUW');

        $this->assertCount(1, $this->sync()->renamed);
        $this->assertSame('Pseudo#EUW', $this->riotIdOf('puuid-a'));
    }

    public function testUnRiotIdDejaAJourNEstPasReecrit(): void
    {
        $this->accounts->add(new TrackedRiotAccount('Pseudo#EUW', 'puuid-a', null));
        $this->directory->knows('puuid-a', 'Pseudo#EUW');

        $report = $this->sync();

        $this->assertSame([], $report->renamed);
        $this->assertSame(1, $report->unchanged);
        $this->assertSame([], $this->logger->records('info'));
    }

    public function testUnCompteEnEchecGardeSonRiotIdSansBloquerLesAutres(): void
    {
        $this->accounts
            ->add(new TrackedRiotAccount('Panne#EUW', 'puuid-panne', null))
            ->add(new TrackedRiotAccount('ancien-id-technique', 'puuid-b', null));
        $this->directory->failsFor('puuid-panne')->knows('puuid-b', 'Bravo#EUW');

        $report = $this->sync();

        $this->assertSame('Panne#EUW', $this->riotIdOf('puuid-panne'));
        $this->assertSame('Bravo#EUW', $this->riotIdOf('puuid-b'));
        $this->assertSame(['Panne#EUW'], $report->failed);
        $this->assertSame(['puuid-panne', 'puuid-b'], $this->directory->lookups);
        $this->assertSame(['riotId' => 'Panne#EUW'], array_diff_key($this->logger->records('warning')[0]['context'], ['exception' => true]));
    }

    public function testUnPuuidInconnuDeRiotGardeSonRiotId(): void
    {
        $this->accounts->add(new TrackedRiotAccount('Fantome#EUW', 'puuid-fantome', null));

        $report = $this->sync();

        $this->assertSame('Fantome#EUW', $this->riotIdOf('puuid-fantome'));
        $this->assertSame(['Fantome#EUW'], $report->failed);
        $this->assertSame(['riotId' => 'Fantome#EUW', 'puuid' => 'puuid-fantome'], $this->logger->records('warning')[0]['context']);
    }

    public function testUnRiotIdDejaPorteParUnAutreCompteNEstPasDuplique(): void
    {
        $this->accounts
            ->add(new TrackedRiotAccount('Pseudo#EUW', 'puuid-a', null))
            ->add(new TrackedRiotAccount('doublon-technique', 'puuid-b', null));
        $this->directory->knows('puuid-a', 'Pseudo#EUW')->knows('puuid-b', 'pseudo#euw');

        $report = $this->sync();

        $this->assertSame('doublon-technique', $this->riotIdOf('puuid-b'));
        $this->assertSame(['doublon-technique'], $report->failed);
        $this->assertSame('puuid-a', $this->logger->records('warning')[0]['context']['holderPuuid']);
    }

    public function testChaqueMiseAJourEstJournalisee(): void
    {
        $this->accounts->add(new TrackedRiotAccount('Ancien#EUW', 'puuid-a', null));
        $this->directory->knows('puuid-a', 'Nouveau#EUW');

        $this->sync();

        $this->assertSame([['level' => 'info', 'message' => 'Riot ID mis à jour depuis Riot', 'context' => ['from' => 'Ancien#EUW', 'to' => 'Nouveau#EUW']]], $this->logger->records('info'));
    }
}
