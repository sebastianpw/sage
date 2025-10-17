<?php

namespace App\Entity;

use App\Repository\ChatSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'chat_session')]
#[ORM\UniqueConstraint(name: 'session_id', columns: ['session_id'])]
#[ORM\Entity(repositoryClass: ChatSessionRepository::class)]
class ChatSession
{
    public const TYPE_STANDARD = 'standard';
    public const TYPE_GENERATOR = 'generator';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "session_id", length: 36)]
    private ?string $sessionId = null;

    #[ORM\Column(name: "created_at", type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "chatSessions")]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: true)]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'session', targetEntity: ChatSummary::class, cascade: ['persist', 'remove'])]
    private Collection $summaries;

    #[ORM\OneToMany(mappedBy: 'session', targetEntity: ChatMessage::class, cascade: ['persist', 'remove'])]
    private Collection $messages;

    #[ORM\Column(type: "string", length: 50, options: ["default" => ''])]
    private string $model = '';

    // ---------- NEW: type column to mark generator sessions ----------
    #[ORM\Column(type: "string", length: 32, options: ["default" => self::TYPE_STANDARD])]
    private string $type = self::TYPE_STANDARD;
    // ---------------------------------------------------------------

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->summaries = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): static
    {
        $this->sessionId = $sessionId;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    // title block remains unchanged
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $title = null;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    // ---------- NEW: type getter/setter ----------
    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        // small safety: only allow known values (you can relax if needed)
        if (!in_array($type, [self::TYPE_STANDARD, self::TYPE_GENERATOR], true)) {
            $this->type = $type; // allow unknown if you want, or throw exception
        } else {
            $this->type = $type;
        }
        return $this;
    }
    // --------------------------------------------

    /**
     * @return Collection<int, ChatSummary>
     */
    public function getSummaries(): Collection
    {
        return $this->summaries;
    }

    public function addSummary(ChatSummary $summary): static
    {
        if (!$this->summaries->contains($summary)) {
            $this->summaries->add($summary);
            $summary->setSession($this);
        }
        return $this;
    }

    public function removeSummary(ChatSummary $summary): static
    {
        if ($this->summaries->removeElement($summary)) {
            if ($summary->getSession() === $this) {
                $summary->setSession(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(ChatMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setSession($this);
        }
        return $this;
    }

    public function removeMessage(ChatMessage $message): static
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getSession() === $this) {
                $message->setSession(null);
            }
        }
        return $this;
    }
}
