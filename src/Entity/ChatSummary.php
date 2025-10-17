<?php

namespace App\Entity;

use App\Repository\ChatSummaryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatSummaryRepository::class)]
#[ORM\Table(name: 'chat_summary')]
#[ORM\Index(name: 'session_id', columns: ['session_id'])]
class ChatSummary
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Fix: ManyToOne (many summaries per session), FK references id (PK)
    #[ORM\ManyToOne(targetEntity: ChatSession::class, inversedBy: 'summaries')]
    #[ORM\JoinColumn(name: "session_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private ?ChatSession $session = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $summary = null;

    #[ORM\Column(type: 'integer', nullable: true, options: ["default" => null])]
    private ?int $tokens = null;


    #[ORM\Column(name: "created_at", type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?\DateTimeInterface $createdAt = null;


    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): ?ChatSession
    {
        return $this->session;
    }

    public function setSession(ChatSession $session): static
    {
        $this->session = $session;
        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): static
    {
        $this->summary = $summary;
        return $this;
    }

    public function getTokens(): ?int
    {
        return $this->tokens;
    }

    public function setTokens(?int $tokens): static
    {
        $this->tokens = $tokens;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
