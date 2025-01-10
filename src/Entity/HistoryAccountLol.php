<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\HistoryAccountLolRepository;
use App\State\RiotAccountProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints\Json;

#[ORM\Entity(repositoryClass: HistoryAccountLolRepository::class)]
#[ApiResource]
class HistoryAccountLol
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private $data = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\ManyToOne(inversedBy: 'historyAccountLols')]
    #[ORM\JoinColumn(nullable: false)]
    private ?RiotAccount $riotAccount = null;

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
}
