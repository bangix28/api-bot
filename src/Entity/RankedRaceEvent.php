<?php

namespace App\Entity;

use App\Domain\EloSnapshot\RankedQueueType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Événement Ranked Race créé en administration. Pas de repositoryClass :
 * la lecture applicative passe par le port RaceEventRepositoryInterface.
 * Les contraintes Assert sont évaluées par les formulaires EasyAdmin.
 */
#[ORM\Entity]
class RankedRaceEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de l\'événement est obligatoire.')]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startDate', message: 'La date de fin doit être postérieure ou égale à la date de début.')]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(length: 20, enumType: RankedQueueType::class)]
    #[Assert\NotNull]
    private ?RankedQueueType $queueType = null;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(1, message: 'Le seuil de qualification doit être d\'au moins 1 partie.')]
    private ?int $minGamesToQualify = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getQueueType(): ?RankedQueueType
    {
        return $this->queueType;
    }

    public function setQueueType(?RankedQueueType $queueType): static
    {
        $this->queueType = $queueType;

        return $this;
    }

    public function getMinGamesToQualify(): ?int
    {
        return $this->minGamesToQualify;
    }

    public function setMinGamesToQualify(?int $minGamesToQualify): static
    {
        $this->minGamesToQualify = $minGamesToQualify;

        return $this;
    }
}
