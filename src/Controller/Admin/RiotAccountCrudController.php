<?php

namespace App\Controller\Admin;

use App\Application\EloSnapshot\SnapshotDailyElo\SnapshotDailyEloHandler;
use App\Application\RiotAccount\RefreshData\RefreshRiotAccountDataHandler;
use App\Entity\RiotAccount;
use App\Infrastructure\RiotAccount\RiotAccountDefaults;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RiotAccountCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly RefreshRiotAccountDataHandler $refreshHandler,
        private readonly SnapshotDailyEloHandler $snapshotDailyElo,
        private readonly LoggerInterface $refreshLogger = new NullLogger(),
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return RiotAccount::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Compte Riot')
            ->setEntityLabelInPlural('Comptes Riot')
            ->setDefaultSort(['score' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        // Seules l'identité et le pseudo sont saisis : toutes les colonnes ranked
        // viennent de l'API Riot (cf. persistEntity). Les laisser au formulaire,
        // c'est ce qui produisait des lignes à NULL illisibles par le domaine.
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('riotId', 'Riot ID')
            ->setRequired(true)
            ->setHelp('Format Pseudo#TAG.');
        yield TextField::new('puuid', 'PUUID')
            ->setRequired(true)
            ->setHelp("Identifiant Riot du joueur. Le projet ne sait pas le déduire du Riot ID : à récupérer via un outil externe.")
            ->hideOnIndex();
        yield TextField::new('summonerName', 'Nom d\'invocateur')
            ->setRequired(false)
            ->setHelp('Laisser vide pour reprendre la partie avant le # du Riot ID.');

        yield TextField::new('summonerRankedSoloTier', 'Palier solo')->hideOnForm();
        yield TextField::new('summonerRankedSoloRank', 'Division solo')->hideOnForm();
        yield TextField::new('summonerRankedSoloLeaguePoints', 'LP solo')->hideOnForm();
        yield IntegerField::new('summoner_ranked_solo_wins', 'Victoires solo')->hideOnForm();
        yield TextField::new('summonerRankedSoloLosses', 'Défaites solo')->hideOnForm();
        yield IntegerField::new('score', 'Score')->hideOnForm();
        yield IntegerField::new('summonerLevel', 'Niveau')->hideOnForm();
        yield DateTimeField::new('lastUpdate', 'Dernier refresh')->hideOnForm();

        // Détail seulement : utile au diagnostic, illisible dans le tableau d'index.
        yield TextField::new('logoId', 'Icône')->onlyOnDetail();
        yield BooleanField::new('soloHotStreak', 'Hot streak')->onlyOnDetail();
        yield BooleanField::new('soloVeteran', 'Vétéran')->onlyOnDetail();
        yield BooleanField::new('soloFreshBlood', 'Sang frais')->onlyOnDetail();
        yield IntegerField::new('soloMiniSeriesWins', 'Bo5 — victoires')->onlyOnDetail();
        yield IntegerField::new('soloMiniSeriesLosses', 'Bo5 — défaites')->onlyOnDetail();
        yield IntegerField::new('soloMiniSeriesTarget', 'Bo5 — objectif')->onlyOnDetail();
        yield TextField::new('soloMiniSeriesProgress', 'Bo5 — progression')->onlyOnDetail();
        yield TextField::new('summonerRankedFlexTier', 'Palier flex')->onlyOnDetail();
        yield TextField::new('summonerRankedFlexRank', 'Division flex')->onlyOnDetail();
        yield IntegerField::new('summonerRankedFlexLeaguePoints', 'LP flex')->onlyOnDetail();
        yield IntegerField::new('summonerRankedFlexWins', 'Victoires flex')->onlyOnDetail();
        yield IntegerField::new('summonerRankedFlexLosses', 'Défaites flex')->onlyOnDetail();
    }

    public function createEntity(string $entityFqcn): object
    {
        // Défauts posés avant le binding du formulaire : les colonnes ranked étant
        // absentes du form, elles ne seront pas écrasées par une saisie vide.
        return RiotAccountDefaults::applyUnranked(new RiotAccount());
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof RiotAccount) {
            parent::persistEntity($entityManager, $entityInstance);

            return;
        }

        if (($entityInstance->getSummonerName() ?? '') === '') {
            $entityInstance->setSummonerName(RiotAccountDefaults::summonerNameFrom($entityInstance->getRiotId()));
        }

        // INSERT d'abord : le refresh relit la ligne par son puuid et save() la
        // retrouve par son riotId, donc elle doit déjà exister en base.
        parent::persistEntity($entityManager, $entityInstance);

        if (!$this->enrichFromRiot($entityInstance)) {
            return;
        }

        // Snapshot initial : le joueur entre dans la Ranked Race dès son inscription,
        // sans attendre le cron de 3h. Inutile de tenter si Riot vient d'échouer.
        try {
            $this->snapshotDailyElo->handleOne($entityInstance->getPuuid());
        } catch (\Throwable $exception) {
            $this->refreshLogger->warning('Snapshot elo initial impossible à la création du compte', [
                'puuid' => $entityInstance->getPuuid(),
                'exception' => $exception,
            ]);
            $this->addFlash('warning', "Données Riot récupérées, mais le snapshot elo initial a échoué : le joueur entrera dans la Ranked Race au prochain passage du cron.");
        }
    }

    /**
     * Best effort : un échec Riot ne doit pas annuler la création. La ligne reste
     * dans sa représentation « non classé » (valide, lisible) et le prochain
     * refreshSummoners la complétera.
     */
    private function enrichFromRiot(RiotAccount $riotAccount): bool
    {
        try {
            // \Throwable et non \Exception : une donnée Riot inattendue peut lever un
            // TypeError dans le mapping, et une création ne doit jamais rendre une 500.
            $this->refreshHandler->handleOne($riotAccount->getPuuid());
        } catch (\Throwable $exception) {
            $this->refreshLogger->warning('Récupération Riot impossible à la création du compte', [
                'puuid' => $riotAccount->getPuuid(),
                'exception' => $exception,
            ]);
            $this->addFlash('warning', "Compte créé, mais ses données Riot n'ont pas pu être récupérées (PUUID invalide ou API indisponible). Il reste « non classé » et sera complété au prochain refresh automatique.");

            return false;
        }

        $this->addFlash('success', 'Données Riot récupérées.');

        return true;
    }
}
