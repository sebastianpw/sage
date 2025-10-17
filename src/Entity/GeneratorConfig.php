<?php
// src/Entity/GeneratorConfig.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'generator_config')]
#[ORM\HasLifecycleCallbacks]
class GeneratorConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'config_id', type: 'string', length: 64, unique: true)]
    private string $configId;

    #[ORM\Column(name: 'user_id', type: 'integer')]
    private int $userId;

    #[ORM\Column(name: 'title', type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(name: 'model', type: 'string', length: 100)]
    private string $model = 'openai';

    #[ORM\Column(name: 'system_role', type: 'text')]
    private string $systemRole;

    #[ORM\Column(name: 'instructions', type: 'json')]
    private array $instructions = [];

    #[ORM\Column(name: 'parameters', type: 'json')]
    private array $parameters = [];

    #[ORM\Column(name: 'output_schema', type: 'json')]
    private array $outputSchema = [];

    #[ORM\Column(name: 'examples', type: 'json', nullable: true)]
    private ?array $examples = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(name: 'active', type: 'boolean')]
    private bool $active = true;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->configId = bin2hex(random_bytes(16));
    }

    // --- Getters / Setters ---
    public function getId(): ?int { return $this->id; }

    public function getConfigId(): string { return $this->configId; }
    public function setConfigId(string $configId): self { $this->configId = $configId; return $this; }

    public function getUserId(): int { return $this->userId; }
    public function setUserId(int $userId): self { $this->userId = $userId; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getModel(): string { return $this->model; }
    public function setModel(string $model): self { $this->model = $model; return $this; }

    public function getSystemRole(): string { return $this->systemRole; }
    public function setSystemRole(string $systemRole): self { $this->systemRole = $systemRole; return $this; }

    public function getInstructions(): array { return $this->instructions; }
    public function setInstructions(array $instructions): self { $this->instructions = $instructions; return $this; }

    public function getParameters(): array { return $this->parameters; }
    public function setParameters(array $parameters): self { $this->parameters = $parameters; return $this; }

    public function getOutputSchema(): array { return $this->outputSchema; }
    public function setOutputSchema(array $outputSchema): self { $this->outputSchema = $outputSchema; return $this; }

    public function getExamples(): ?array { return $this->examples; }
    public function setExamples(?array $examples): self { $this->examples = $examples; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }

    // --- Utility ---
    public function toConfigArray(): array
    {
        return [
            'system' => [
                'role' => $this->systemRole,
                'instructions' => $this->instructions,
            ],
            'parameters' => $this->parameters,
            'output' => $this->outputSchema,
            'examples' => $this->examples ?? [],
        ];
    }

    public static function fromJson(string $json, int $userId): self
    {
        $data = json_decode($json, true);
        if (!$data) {
            throw new \InvalidArgumentException('Invalid JSON');
        }

        $config = new self();
        $config->setUserId($userId);
        $config->setSystemRole($data['system']['role'] ?? '');
        $config->setInstructions($data['system']['instructions'] ?? []);
        $config->setParameters($data['parameters'] ?? []);
        $config->setOutputSchema($data['output'] ?? []);
        $config->setExamples($data['examples'] ?? null);

        return $config;
    }

    // --- Lifecycle callbacks ---
    #[ORM\PreUpdate]
    public function preUpdateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
