<?php

namespace App\Core;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ChatSession;
use App\Entity\ChatMessage;
use App\Entity\ChatSummary;

/**
 * ChatManager (Refactored - Decoupled)
 *
 * Now uses AIProvider for all AI communication.
 * Focuses purely on chat session management and persistence.
 *
 * MODEL LIST (quick switch / copy-paste here)
 *
 * // --- Provided model list (IDs only) ---
 * // llama-3.3-70b-versatile
 * // meta-llama/llama-guard-4-12b
 * // playai-tts
 * // openai/gpt-oss-120b
 * // meta-llama/llama-prompt-guard-2-86m
 * // allam-2-7b
 * // moonshotai/kimi-k2-instruct-0905
 * // whisper-large-v3-turbo
 * // whisper-large-v3
 * // playai-tts-arabic
 * // meta-llama/llama-prompt-guard-2-22m
 * // qwen/qwen3-32b
 * // llama-3.1-8b-instant
 * // groq/compound
 * // moonshotai/kimi-k2-instruct
 * // meta-llama/llama-4-scout-17b-16e-instruct
 * // groq/compound-mini
 * // openai/gpt-oss-20b
 * // meta-llama/llama-4-maverick-17b-128e-instruct
 */

class ChatManager
{
    private EntityManagerInterface $em;
    private AIProvider $aiProvider;

    private const DEFAULT_MODEL = 'openai';

    public function __construct(EntityManagerInterface $em, ?AIProvider $aiProvider = null)
    {
        $this->em = $em;
        $this->aiProvider = $aiProvider ?? new AIProvider();
    }











    /** Load chat session by sessionId or create if missing */
public function getSession(string $sessionId, ?string $defaultModel = null, string $type = ChatSession::TYPE_STANDARD): ChatSession
{
    $session = $this->em->getRepository(ChatSession::class)
        ->findOneBy(['sessionId' => $sessionId]);

    if (!$session) {
        $session = new ChatSession();
        $session->setSessionId($sessionId);
        $session->setCreatedAt(new \DateTimeImmutable());

        if ($defaultModel !== null) {
            $session->setModel($defaultModel);
        }

        // set type on create
        $session->setType($type);

        $this->em->persist($session);
        $this->em->flush();
    }

    return $session;
}








    /** Load chat session by sessionId or create if missing *
    public function getSession(string $sessionId, ?string $defaultModel = null): ChatSession
    {
        $session = $this->em->getRepository(ChatSession::class)
            ->findOneBy(['sessionId' => $sessionId]);

        if (!$session) {
            $session = new ChatSession();
            $session->setSessionId($sessionId);
            $session->setCreatedAt(new \DateTimeImmutable());
            
            // Set model if provided
            if ($defaultModel !== null) {
                $session->setModel($defaultModel);
            }
            
            $this->em->persist($session);
            $this->em->flush();
        }

        return $session;
    }
     */





    

    /** Fetch all chat messages for a session ordered by creation time ascending */
    public function getMessages(ChatSession $session): array
    {
        return $this->em->getRepository(ChatMessage::class)
            ->findBy(['session' => $session], ['createdAt' => 'ASC']);
    }

    /** Add a new message to a session */
    public function addMessage(ChatSession $session, string $role, string $content, ?int $tokenCount = null): ChatMessage
    {
        $message = new ChatMessage();
        $message->setSession($session);
        $message->setRole($role);
        $message->setContent($content);
        $message->setTokenCount($tokenCount);
        $message->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($message);
        $this->em->flush();

        return $message;
    }

    /** Save a summary for a chat session */
    public function addSummary(ChatSession $session, string $summary, ?int $tokens = null): ChatSummary
    {
        $chatSummary = new ChatSummary();
        $chatSummary->setSession($session);
        $chatSummary->setSummary($summary);
        $chatSummary->setTokens($tokens);
        $chatSummary->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($chatSummary);
        $this->em->flush();

        return $chatSummary;
    }

    /** Get latest summary for a session or null if none exists */
    public function getLatestSummary(ChatSession $session): ?ChatSummary
    {
        return $this->em->getRepository(ChatSummary::class)
            ->findOneBy(['session' => $session], ['createdAt' => 'DESC']);
    }

    /** Build conversation context for sending to API */
    public function buildContext(ChatSession $session, int $maxTokens = 3000): array
    {
        $messages = $this->getMessages($session);
        $contextMessages = [];
        $tokenCount = 0;

        foreach ($messages as $msg) {
            $msgTokens = $msg->getTokenCount() ?? 0;
            if ($tokenCount + $msgTokens > $maxTokens) {
                break;
            }
            $tokenCount += $msgTokens;

            $contextMessages[] = [
                'role' => strtolower($msg->getRole()),
                'content' => $msg->getContent(),
            ];
        }

        return ['messages' => $contextMessages];
    }

    /** Call AI API using the centralized AIProvider */
    public function sendMessageToApi(ChatSession $session, array $messages): string
    {
        $model = $session->getModel() ?: self::DEFAULT_MODEL;
        
        try {
            return $this->aiProvider->sendMessage($model, $messages);
        } catch (\RuntimeException $e) {
            // Log the error and rethrow with context
            error_log("ChatManager: AI call failed for model {$model}: " . $e->getMessage());
            throw $e;
        }
    }

    /** Set model for a session */
    public function setModel(ChatSession $session, string $model): void
    {
        $session->setModel($model);
        $this->em->persist($session);
        $this->em->flush();
    }

    /** Handle a user message: persist, send to API, save reply */
    public function handleUserMessage(ChatSession $session, string $userInput): string
    {
        $this->addMessage($session, 'user', $userInput);
        $context = $this->buildContext($session);
        $assistantReply = $this->sendMessageToApi($session, $context['messages']);

        $assistantReply = $this->filterSponsoredContent($assistantReply);

        $this->addMessage($session, 'assistant', $assistantReply);

        return $assistantReply;
    }

    /** Get conversation as plain text */
    public function getConversationText(ChatSession $session): string
    {
        $messages = $this->getMessages($session);
        $text = "";

        foreach ($messages as $msg) {
            $text .= ucfirst($msg->getRole()) . ": " . $msg->getContent() . "\n";
        }

        return trim($text);
    }

    /** Filter out sponsored content from AI responses */
    public function filterSponsoredContent(string $text): string
    {
        $pos = stripos($text, '**Sponsor**');
        if ($pos !== false) {
            return trim(substr($text, 0, $pos));
        }
        return $text;
    }
    
    /**
     * Get the AIProvider instance (useful for direct access if needed)
     */
    public function getAIProvider(): AIProvider
    {
        return $this->aiProvider;
    }
}	
