<?php

namespace App\Core;

use App\Core\SpwBase;
use App\Entity\User;
use App\Entity\ChatSession;

class ChatUiAjax
{
    protected int $userId;
    protected SpwBase $spw;
    protected $chatManager;
    protected $em;
    protected User $user;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->spw = SpwBase::getInstance();
        $this->chatManager = $this->spw->getChatManager();
        $this->em = $this->spw->getEntityManager();

        $user = $this->em->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new \Exception("Invalid user");
        }
        $this->user = $user;
    }

    public function handle(array $post): array
    {
        try {
            if (isset($post['new_chat'])) {
                $model = !empty($post['model']) ? trim($post['model']) : null;
                return $this->createNewChat($model);
            }
            if (!empty($post['copy_chat'])) {
                return $this->copyChat($post['copy_chat']);
            }
            if (isset($post['list_chats'])) {
                return $this->listChats();
            }
            if (!empty($post['load_chat'])) {
                return $this->loadChatMessages($post['load_chat']);
            }
            if (!empty($post['message']) && !empty($post['chat_session_id'])) {
                return $this->sendMessage($post['chat_session_id'], trim($post['message']));
            }
            if (!empty($post['update_title']) && !empty($post['chat_session_id'])) {
                return $this->updateChatTitle($post['chat_session_id'], trim($post['update_title']));
            }
            if (!empty($post['delete_chat'])) {
                return $this->deleteChat($post['delete_chat']);
            }
            if (!empty($post['delete_message']) && !empty($post['chat_session_id'])) {
                return $this->deleteMessage($post['delete_message'], $post['chat_session_id']);
            }

            return ['error' => 'No valid action found'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // --- Private helper methods ---

    private function createNewChat(?string $model = null): array
    {
        $sessionId = bin2hex(random_bytes(16));
        $session = new ChatSession();
        $session->setSessionId($sessionId);
        $session->setUser($this->user);
        
        // Set model if provided, otherwise use default
        if ($model !== null) {
            $session->setTitle($model);
            $session->setModel($model);
        }

        $this->em->persist($session);
        $this->em->flush();

        return [
            'chat_session_id' => $sessionId,
            'model' => $session->getModel()
        ];
    }

    private function copyChat(string $originalSessionId): array
    {
        $originalSession = $this->chatManager->getSession($originalSessionId);
        if ($originalSession->getUser()->getId() !== $this->userId) {
            return ['error' => 'Access denied.'];
        }

        $newSessionId = bin2hex(random_bytes(16));
        $newSession = new ChatSession();
        $newSession->setSessionId($newSessionId);
        $newSession->setUser($this->user);
        
        // Copy model from original session
        if ($originalSession->getModel()) {
            $newSession->setModel($originalSession->getModel());
        }
        
        $this->em->persist($newSession);
        $this->em->flush();

        $messages = $this->chatManager->getMessages($originalSession);
        foreach ($messages as $msg) {
            $this->chatManager->addMessage($newSession, $msg->getRole(), $msg->getContent());
        }
        $this->em->flush();

        return ['chat_session_id' => $newSessionId];
    }

    private function listChats(): array
    {
        $sessions = $this->em->getRepository(ChatSession::class)
            ->findBy(['user' => $this->user, 'type' => 'standard'], ['createdAt' => 'DESC']);

        $list = [];
        foreach ($sessions as $s) {
            $list[] = [
                'id' => $s->getSessionId(),
                'created_at' => $s->getCreatedAt()->format('Y-m-d H:i:s'),
                'title' => $s->getTitle() ?? '(untitled)',
                'model' => $s->getModel() ?? 'default'
            ];
        }

        return ['sessions' => $list];
    }

    private function loadChatMessages(string $sessionId): array
    {
        $session = $this->chatManager->getSession($sessionId);
        if ($session->getUser()->getId() !== $this->userId) {
            return ['history' => []];
        }

        $messages = $this->chatManager->getMessages($session);

        $history = [];
        foreach ($messages as $msg) {
            $history[] = [
                'id' => $msg->getId(),
                'role' => strtolower($msg->getRole()),
                'content' => $msg->getContent()
            ];
        }

        return [
            'history' => $history,
            'model' => $session->getModel() ?? 'default'
        ];
    }

    private function sendMessage(string $sessionId, string $message): array
    {
        $session = $this->chatManager->getSession($sessionId);
        if ($session->getUser()->getId() !== $this->userId) {
            return ['answer' => 'Error: Access denied.'];
        }

        if (!$this->em->contains($session)) {
            $session = $this->em->merge($session);
        }

        // Use session's model or default
        $model = $session->getModel() ?? ChatManager::DEFAULT_MODEL;
        $this->chatManager->setModel($session, $model);

        // Persist user message
        $this->em->flush();

        // Generate assistant reply
        $assistantReply = $this->chatManager->handleUserMessage($session, $message);

        // Persist assistant message
        $this->em->flush();

        return ['answer' => $assistantReply];
    }

    private function updateChatTitle(string $sessionId, string $title): array
    {
        $session = $this->chatManager->getSession($sessionId);
        if ($session->getUser()->getId() !== $this->userId) {
            return ['error' => 'Access denied.'];
        }

        $session->setTitle($title);
        $this->em->persist($session);
        $this->em->flush();

        return ['success' => true, 'title' => $title];
    }

    private function deleteChat(string $sessionId): array
    {
        $session = $this->chatManager->getSession($sessionId);
        if ($session->getUser()->getId() !== $this->userId) {
            return ['error' => 'Access denied.'];
        }

        $this->em->remove($session);
        $this->em->flush();

        return ['success' => true];
    }

    private function deleteMessage(string $messageId, string $sessionId): array
    {
        $session = $this->chatManager->getSession($sessionId);
        if ($session->getUser()->getId() !== $this->userId) {
            return ['error' => 'Access denied.'];
        }

        $message = $this->em->getRepository(\App\Entity\ChatMessage::class)->find($messageId);
        if (!$message || $message->getSession()->getId() !== $session->getId()) {
            return ['error' => 'Message not found'];
        }

        $this->em->remove($message);
        $this->em->flush();

        return ['success' => true];
    }
}
