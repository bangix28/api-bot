<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\HistoryAccountLolRepository;
use App\State\RiotAccountProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Json;

#[ORM\Entity(repositoryClass: HistoryAccountLolRepository::class)]
#[ApiResource(
    uriTemplate: '/riot-account/{id}/history-account-lol',
    operations: [ new GetCollection() ],
    normalizationContext: ['groups' => ['historyAccount:read:get']],
    paginationItemsPerPage: 3
)]
class HistoryAccountLol
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['historyAccount:read:get'])]
    private ?int $id = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private $data = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\ManyToOne(inversedBy: 'historyAccountLols')]
    #[ORM\JoinColumn(nullable: false)]
    private ?RiotAccount $riotAccount = null;

    #[ORM\Column]
    private ?bool $isWin = null;

    #[ORM\Column]
    private ?int $championId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateGameEnd = null;

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getData(): ?array
    {
        if ($this->data === null) {
            return null;
        }

        return json_decode($this->data, true);
    }

    public function setData(array $data): static
    {
        $jsonData = json_encode($data);

        $this->data = $jsonData;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

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

    public function isWin(): ?bool
    {
        return $this->isWin;
    }

    public function setIsWin(bool $isWin): static
    {
        $this->isWin = $isWin;

        return $this;
    }

    public function getChampionId(): ?int
    {
        return $this->championId;
    }

    public function setChampionId(int $championId): static
    {
        $this->championId = $championId;

        return $this;
    }

    public function getDateGameEnd(): ?\DateTimeInterface
    {
        return $this->dateGameEnd;
    }

    public function setDateGameEnd(\DateTimeInterface $dateGameEnd): static
    {
        $this->dateGameEnd = $dateGameEnd;

        return $this;
    }
}
