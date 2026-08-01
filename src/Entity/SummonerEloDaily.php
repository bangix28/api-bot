<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Repository\SummonerEloDailyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Entity(repositoryClass: SummonerEloDailyRepository::class)]
#[UniqueEntity(fields: ['riot_account_id', 'date_score'])]
#[ApiResource(
    operations: [
        // Courbe d'évolution quotidienne de l'elo (score = tier*1000 + division*100 + LP).
        // Pagination désactivée : 1 point/jour max (~365/an), le front filtre la période en mémoire.
        new GetCollection(
            uriTemplate: '/riot-account/{id}/elo-daily',
            uriVariables: [
                'id' => new Link(
                    fromProperty: 'summonerEloDailies',
                    fromClass: RiotAccount::class
                )
            ],
            normalizationContext: ['groups' => ['eloDaily:read:get']],
            order: ['dateScore' => 'ASC'],
            paginationEnabled: false
        ),
    ]
)]
class SummonerEloDaily
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['eloDaily:read:get'])]
    private ?string $score = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['eloDaily:read:get'])]
    private ?\DateTimeInterface $dateScore = null;

    #[ORM\ManyToOne(inversedBy: 'summonerEloDailies')]
    #[ORM\JoinColumn(nullable: false)]
    private ?RiotAccount $riotAccount = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScore(): ?string
    {
        return $this->score;
    }

    public function setScore(string $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getDateScore(): ?\DateTimeInterface
    {
        return $this->dateScore;
    }

    public function setDateScore(\DateTimeInterface $dateScore): static
    {
        $this->dateScore = $dateScore;

        return $this;
    }

    public function getRiotAccount(): ?RiotAccount
    {
        return $this->riotAccount;
    }

    public function setRiotAccount(?RiotAccount $riotAccount): static
    {
        $this->riotAccount = $riotAccount;

        return $this;
    }
}
