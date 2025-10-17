<?php
// src/Entity/Character.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "characters")]
#[ORM\UniqueConstraint(name: "uq_characters_name", columns: ["name"])]
#[ORM\Index(name: "idx_characters_role", columns: ["role"])]
class Character
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $ageBackground = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "string", length: 255, options: ["default" => ""])]
    private string $descAbbr = '';

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $motivations = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $hooksArcPotential = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(type: "boolean", options: ["default" => false], comment: "1 = regenerate frames")]
    private bool $regenerateImages = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // --- Getters and Setters ---
    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getAgeBackground(): ?string
    {
        return $this->ageBackground;
    }

    public function setAgeBackground(?string $ageBackground): self
    {
        $this->ageBackground = $ageBackground;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDescAbbr(): string
    {
        return $this->descAbbr;
    }

    public function setDescAbbr(string $descAbbr): self
    {
        $this->descAbbr = $descAbbr;
        return $this;
    }

    public function getMotivations(): ?string
    {
        return $this->motivations;
    }

    public function setMotivations(?string $motivations): self
    {
        $this->motivations = $motivations;
        return $this;
    }

    public function getHooksArcPotential(): ?string
    {
        return $this->hooksArcPotential;
    }

    public function setHooksArcPotential(?string $hooksArcPotential): self
    {
        $this->hooksArcPotential = $hooksArcPotential;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getRegenerateImages(): bool
    {
        return $this->regenerateImages;
    }

    public function setRegenerateImages(bool $regenerateImages): self
    {
        $this->regenerateImages = $regenerateImages;
        return $this;
    }
}


