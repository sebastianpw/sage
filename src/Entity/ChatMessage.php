<?php

namespace App\Entity;

use App\Repository\ChatMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatMessageRepository::class)]
#[ORM\Table(name: 'chat_message')]
#[ORM\Index(name: 'session_id', columns: ['session_id'])]
class ChatMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    // Fix: referencedColumnName should be 'id' (primary key)
    #[ORM\ManyToOne(targetEntity: ChatSession::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: "session_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private ?ChatSession $session = null;

    // Use string with length, not Types::STRING (enum not supported by Doctrine natively)
    #[ORM\Column(type: 'string', length: 10)]
    private ?string $role = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(name: "token_count", nullable: true, options: ["default" => null])]
    private ?int $tokenCount = null;


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

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getTokenCount(): ?int
    {
        return $this->tokenCount;
    }

    public function setTokenCount(?int $tokenCount): static
    {
        $this->tokenCount = $tokenCount;

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
