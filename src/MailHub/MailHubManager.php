<?php
/**
 * SAGE Mail Hub — Manager
 * src/MailHub/MailHubManager.php
 *
 * Central service class. Manages newsletters, subscribers, lists,
 * providers, queue operations, and the send pipeline.
 *
 * Provider abstraction: instantiate any MailProviderInterface driver
 * by calling getProvider($providerRow) — add new drivers here.
 */

namespace App\MailHub;

use App\MailHub\Providers\MailProviderInterface;
use App\MailHub\Providers\BrevoProvider;
use App\MailHub\Providers\SmtpProvider;

class MailHubManager
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ══════════════════════════════════════════════════════════════════════
    // PROVIDER REGISTRY
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Resolve a mail_hub_providers row into a concrete provider instance.
     * Add new drivers here — no changes needed anywhere else.
     */
    public function getProvider(array $providerRow): MailProviderInterface
    {
        $config = is_array($providerRow['config'])
            ? $providerRow['config']
            : (json_decode($providerRow['config'] ?? '{}', true) ?: []);

        return match ($providerRow['driver']) {
            'brevo' => new BrevoProvider($config),
            'smtp'  => new SmtpProvider($config),
            default => throw new \RuntimeException("Unknown mail driver: {$providerRow['driver']}"),
        };
    }

    /** Return all available driver keys and labels for the UI */
    public function getDriverOptions(): array
    {
        return [
            ['key' => 'brevo', 'label' => 'Brevo (API)'],
            ['key' => 'smtp',  'label' => 'SMTP'],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // PROVIDERS CRUD
    // ══════════════════════════════════════════════════════════════════════

    public function getProviders(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, name, driver, is_default, is_enabled, daily_limit, sent_today, last_reset, notes, config, created_at
             FROM mail_hub_providers ORDER BY is_default DESC, id ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['config'] = json_decode($r['config'] ?? '{}', true) ?: [];
        }
        return $rows;
    }

    public function getProviderById(int $id): ?array
    {
        $s = $this->pdo->prepare("SELECT * FROM mail_hub_providers WHERE id = ?");
        $s->execute([$id]);
        $row = $s->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $row['config'] = json_decode($row['config'] ?? '{}', true) ?: [];
        }
        return $row ?: null;
    }

    public function saveProvider(array $data): array
    {
        $id     = !empty($data['id']) ? (int)$data['id'] : null;
        $config = $this->sanitiseJson($data['config'] ?? '{}');

        if ($id) {
            $s = $this->pdo->prepare(
                "UPDATE mail_hub_providers SET
                    name = :name, driver = :driver, is_default = :def,
                    is_enabled = :en, config = :cfg,
                    daily_limit = :dl, notes = :notes, updated_at = NOW()
                 WHERE id = :id"
            );
            $s->execute([
                ':name' => $data['name'], ':driver' => $data['driver'],
                ':def'  => (int)!empty($data['is_default']),
                ':en'   => (int)!empty($data['is_enabled']),
                ':cfg'  => $config,
                ':dl'   => !empty($data['daily_limit']) ? (int)$data['daily_limit'] : null,
                ':notes'=> $data['notes'] ?? null, ':id' => $id,
            ]);
        } else {
            $s = $this->pdo->prepare(
                "INSERT INTO mail_hub_providers
                    (name, driver, is_default, is_enabled, config, daily_limit, notes)
                 VALUES (:name, :driver, :def, :en, :cfg, :dl, :notes)"
            );
            $s->execute([
                ':name' => $data['name'], ':driver' => $data['driver'],
                ':def'  => (int)!empty($data['is_default']),
                ':en'   => (int)!empty($data['is_enabled']),
                ':cfg'  => $config,
                ':dl'   => !empty($data['daily_limit']) ? (int)$data['daily_limit'] : null,
                ':notes'=> $data['notes'] ?? null,
            ]);
            $id = (int)$this->pdo->lastInsertId();
        }

        // Enforce single default
        if (!empty($data['is_default'])) {
            $this->pdo->prepare("UPDATE mail_hub_providers SET is_default = 0 WHERE id != ?")->execute([$id]);
        }

        return ['success' => true, 'id' => $id];
    }

    public function deleteProvider(int $id): array
    {
        $this->pdo->prepare("DELETE FROM mail_hub_providers WHERE id = ?")->execute([$id]);
        return ['success' => true];
    }

    public function getDefaultProvider(): ?array
    {
        $s = $this->pdo->query(
            "SELECT * FROM mail_hub_providers WHERE is_default = 1 AND is_enabled = 1 LIMIT 1"
        );
        $row = $s->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $row['config'] = json_decode($row['config'] ?? '{}', true) ?: [];
        }
        return $row ?: null;
    }

    // ══════════════════════════════════════════════════════════════════════
    // TEMPLATES CRUD
    // ══════════════════════════════════════════════════════════════════════

    public function getTemplates(): array
    {
        return $this->pdo->query(
            "SELECT id, name, created_at, updated_at FROM mail_hub_templates ORDER BY name ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTemplateById(int $id): ?array
    {
        $s = $this->pdo->prepare("SELECT * FROM mail_hub_templates WHERE id = ?");
        $s->execute([$id]);
        return $s->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function saveTemplate(array $data): array
    {
        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $name = trim($data['name'] ?? '');
        if (empty($name)) return ['success' => false, 'error' => 'Template name required'];

        if ($id) {
            $this->pdo->prepare(
                "UPDATE mail_hub_templates SET name = ?, body_html = ?, body_text = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$name, $data['body_html'] ?? '', $data['body_text'] ?? '', $id]);
        } else {
            $this->pdo->prepare(
                "INSERT INTO mail_hub_templates (name, body_html, body_text) VALUES (?, ?, ?)"
            )->execute([$name, $data['body_html'] ?? '', $data['body_text'] ?? '']);
            $id = (int)$this->pdo->lastInsertId();
        }
        return ['success' => true, 'id' => $id];
    }

    public function deleteTemplate(int $id): array
    {
        // Remove link from newsletters to avoid orphans
        $this->pdo->prepare("UPDATE mail_hub_newsletters SET template_id = NULL WHERE template_id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM mail_hub_templates WHERE id = ?")->execute([$id]);
        return ['success' => true];
    }

    // ══════════════════════════════════════════════════════════════════════
    // NEWSLETTERS CRUD
    // ══════════════════════════════════════════════════════════════════════

    public function getNewsletters(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]           = 'n.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]          = '(n.title LIKE :q OR n.subject LIKE :q2)';
            $params[':q']     = '%' . $filters['search'] . '%';
            $params[':q2']    = '%' . $filters['search'] . '%';
        }

        $page    = max(1, (int)($filters['page'] ?? 1));
        $limit   = (int)($filters['limit'] ?? 40);
        $offset  = ($page - 1) * $limit;

        $sql = "SELECT SQL_CALC_FOUND_ROWS
                    n.*,
                    l.name AS list_name,
                    p.name AS provider_name
                FROM mail_hub_newsletters n
                LEFT JOIN mail_hub_lists     l ON l.id = n.list_id
                LEFT JOIN mail_hub_providers p ON p.id = n.provider_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY n.created_at DESC
                LIMIT :limit OFFSET :offset";

        $s = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $s->bindValue($k, $v);
        $s->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $s->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $s->execute();

        $rows  = $s->fetchAll(\PDO::FETCH_ASSOC);
        $total = (int)$this->pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

        return ['success' => true, 'newsletters' => $rows, 'total' => $total, 'page' => $page];
    }

    public function getNewsletterById(int $id): ?array
    {
        $s = $this->pdo->prepare(
            "SELECT n.*, l.name AS list_name, p.name AS provider_name
             FROM mail_hub_newsletters n
             LEFT JOIN mail_hub_lists     l ON l.id = n.list_id
             LEFT JOIN mail_hub_providers p ON p.id = n.provider_id
             WHERE n.id = ?"
        );
        $s->execute([$id]);
        return $s->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function saveNewsletter(array $data): array
    {
        $id = !empty($data['id']) ? (int)$data['id'] : null;

        $scheduledAt = null;
        if (!empty($data['scheduled_at'])) {
            $ts = strtotime($data['scheduled_at']);
            if ($ts) $scheduledAt = date('Y-m-d H:i:s', $ts);
        }

        $fields = [
            'title'       => trim($data['title']       ?? ''),
            'subject'     => trim($data['subject']     ?? ''),
            'preview_text'=> $data['preview_text']     ?? null,
            'body_html'   => $data['body_html']        ?? null,
            'body_text'   => $data['body_text']        ?? null,
            'status'      => $data['status']           ?? 'draft',
            'from_name'   => $data['from_name']        ?? null,
            'from_email'  => $data['from_email']       ?? null,
            'reply_to'    => $data['reply_to']         ?? null,
            'list_id'     => !empty($data['list_id'])    ? (int)$data['list_id'] : null,
            'provider_id' => !empty($data['provider_id'])? (int)$data['provider_id'] : null,
            'template_id' => !empty($data['template_id'])? (int)$data['template_id'] : null,
            'scheduled_at'=> $scheduledAt,
            'notes'       => $data['notes']            ?? null,
        ];

        if (empty($fields['title'])) {
            return ['success' => false, 'error' => 'Title is required'];
        }

        if ($id) {
            $set  = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($fields)));
            $stmt = $this->pdo->prepare("UPDATE mail_hub_newsletters SET $set, updated_at = NOW() WHERE id = :id");
            $fields['id'] = $id;
            $stmt->execute($fields);
        } else {
            $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($fields)));
            $vals = implode(', ', array_map(fn($k) => ":$k", array_keys($fields)));
            $stmt = $this->pdo->prepare("INSERT INTO mail_hub_newsletters ($cols) VALUES ($vals)");
            $stmt->execute($fields);
            $id = (int)$this->pdo->lastInsertId();
        }

        return ['success' => true, 'id' => $id];
    }

    public function deleteNewsletter(int $id): array
    {
        // Only allow deleting drafts / cancelled
        $s = $this->pdo->prepare("SELECT status FROM mail_hub_newsletters WHERE id = ?");
        $s->execute([$id]);
        $row = $s->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return ['success' => false, 'error' => 'Not found'];
        if (!in_array($row['status'], ['draft', 'cancelled'])) {
            return ['success' => false, 'error' => 'Only draft or cancelled newsletters can be deleted'];
        }
        $this->pdo->prepare("DELETE FROM mail_hub_newsletters WHERE id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM mail_hub_queue WHERE newsletter_id = ?")->execute([$id]);
        return ['success' => true];
    }

    public function duplicateNewsletter(int $id): array
    {
        $row = $this->getNewsletterById($id);
        if (!$row) return ['success' => false, 'error' => 'Not found'];

        unset($row['id'], $row['list_name'], $row['provider_name']);
        $row['title']       = 'Copy of ' . $row['title'];
        $row['status']      = 'draft';
        $row['sent_at']     = null;
        $row['scheduled_at']= null;
        $row['total_recipients'] = 0;
        $row['total_sent']  = 0;
        $row['total_failed']= 0;

        return $this->saveNewsletter($row);
    }

    // ══════════════════════════════════════════════════════════════════════
    // QUEUE MANAGEMENT
    // ══════════════════════════════════════════════════════════════════════

    public function enqueueNewsletter(int $newsletterId): array
    {
        $nl = $this->getNewsletterById($newsletterId);
        if (!$nl) return ['success' => false, 'error' => 'Newsletter not found'];

        if (!in_array($nl['status'], ['draft', 'scheduled'])) {
            return ['success' => false, 'error' => 'Newsletter must be draft or scheduled to enqueue'];
        }

        // Resolve recipients
        $subscribers = $this->resolveRecipients($nl['list_id'] ?? null);
        if (empty($subscribers)) {
            return ['success' => false, 'error' => 'No active subscribers found. Please sync from Brevo first.'];
        }

        // Clear any existing pending queue rows for this newsletter
        $this->pdo->prepare(
            "DELETE FROM mail_hub_queue WHERE newsletter_id = ? AND status = 'pending'"
        )->execute([$newsletterId]);

        $insert = $this->pdo->prepare(
            "INSERT INTO mail_hub_queue (newsletter_id, subscriber_id, provider_id, scheduled_at)
             VALUES (?, ?, ?, ?)"
        );

        $provId      = !empty($nl['provider_id']) ? (int)$nl['provider_id'] : null;
        $scheduledAt = $nl['scheduled_at'] ?: null;
        $count       = 0;

        foreach ($subscribers as $sub) {
            $insert->execute([
                $newsletterId,
                $sub['id'],
                $provId,
                $scheduledAt,
            ]);
            $count++;
        }



        // Update newsletter status and recipient count
        $this->pdo->prepare(
            "UPDATE mail_hub_newsletters SET
                status = 'sending',
                total_recipients = ?,
                total_sent = 0,
                total_failed = 0,
                updated_at = NOW()
             WHERE id = ?"
        )->execute([$count, $newsletterId]);

        return ['success' => true, 'queued' => $count];
    }

    public function processBatch(int $batchSize = 20, ?int $newsletterId = null): array
    {
        $where  = "status = 'pending' AND (scheduled_at IS NULL OR scheduled_at <= NOW())";
        $params = [];

        if ($newsletterId !== null) {
            $where  .= " AND newsletter_id = ?";
            $params[] = $newsletterId;
        }

        $params[] = $batchSize;

        $stmt = $this->pdo->prepare(
            "SELECT q.*, n.subject, n.body_html, n.body_text, n.from_name, n.from_email, n.reply_to, n.preview_text,
                    t.body_html AS tpl_html, t.body_text AS tpl_text
             FROM mail_hub_queue q
             JOIN mail_hub_newsletters n ON n.id = q.newsletter_id
             LEFT JOIN mail_hub_templates t ON t.id = n.template_id
             WHERE $where
             ORDER BY q.priority DESC, q.id ASC
             LIMIT ?"
        );
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($items)) {
            return ['success' => true, 'processed' => 0, 'sent' => 0, 'failed' => 0];
        }

        $sentCount   = 0;
        $failedCount = 0;
        $providerCache = [];

        foreach ($items as $item) {
            $qId = (int)$item['id'];

            // Mark processing
            $this->pdo->prepare(
                "UPDATE mail_hub_queue SET status = 'processing', started_at = NOW() WHERE id = ?"
            )->execute([$qId]);

            // Resolve provider
            $provId = !empty($item['provider_id']) ? (int)$item['provider_id'] : null;
            if (!$provId) {
                $defaultProv = $this->getDefaultProvider();
                if ($defaultProv) $provId = (int)$defaultProv['id'];
            }

            if (!isset($providerCache[$provId])) {
                $provRow = $provId ? $this->getProviderById($provId) : null;
                if (!$provRow) {
                    $this->failQueueItem($qId, $item, 'No configured mail provider found');
                    $failedCount++;
                    continue;
                }
                if (!$this->checkAndIncrementDailyLimit($provRow)) {
                    $this->pdo->prepare(
                        "UPDATE mail_hub_queue SET status = 'pending', started_at = NULL WHERE id = ?"
                    )->execute([$qId]);
                    continue; // Skip — will retry tomorrow
                }
                $providerCache[$provId] = [
                    'row'      => $provRow,
                    'instance' => $this->getProvider($provRow),
                ];
            }
            
            
            
            

            $driver = $providerCache[$provId]['instance'];

            // ZERO-PII: Fetch live email and status from Brevo JIT (Just-In-Time)
            $contactId = (int)$item['subscriber_id'];
            $brevoContact = $this->brevoApiGet("/contacts/{$contactId}");
            
            if (empty($brevoContact['email']) || !empty($brevoContact['emailBlacklisted'])) {
                $this->failQueueItem($qId, $item, 'Contact invalid or unsubscribed in Brevo');
                $failedCount++;
                continue;
            }
            $item['email'] = $brevoContact['email'];

            // Template wrapping
            $html = $item['body_html'] ?? '';
            
            
            
            
            
            
            $text = $item['body_text'] ?? strip_tags($html);
            
            if (!empty($item['tpl_html'])) {
                $html = str_replace('{{content}}', $html, $item['tpl_html']);
            }
            if (!empty($item['tpl_text'])) {
                $text = str_replace('{{content}}', $text, $item['tpl_text']);
            }

            // Build subject with preview preheader (invisible spacer trick)
            if (!empty($item['preview_text'])) {
                $spacer = str_repeat('&nbsp;&zwnj;', 130);
                $html   = '<div style="display:none;max-height:0;overflow:hidden;">'
                        . htmlspecialchars($item['preview_text'])
                        . $spacer
                        . '</div>'
                        . $html;
            }

            // Inject unsubscribe link token into HTML
            $token = $this->ensureUnsubToken((int)$item['subscriber_id'], (int)$item['newsletter_id']);
            $unsub = $this->buildUnsubscribeUrl($token);
            $html  = $this->injectUnsubscribeLink($html, $unsub);

            $message = [
                'to_email'   => $item['email'],
                'to_name'    => null,
                'from_email' => $item['from_email'] ?: ($providerCache[$provId]['row']['config']['default_from'] ?? ''),
                'from_name'  => $item['from_name']  ?: ($providerCache[$provId]['row']['config']['default_name'] ?? ''),
                'reply_to'   => $item['reply_to']   ?: null,
                'subject'    => $item['subject'],
                'html'       => $html,
                'text'       => $text,
                'headers'    => [
                    'List-Unsubscribe' => '<' . $unsub . '>',
                ],
            ];

            $result = $driver->send($message);

            if ($result['success']) {
                $this->pdo->prepare(
                    "UPDATE mail_hub_queue SET
                        status = 'sent', sent_at = NOW(),
                        provider_msg_id = ?, provider_id = ?, error_msg = NULL
                     WHERE id = ?"
                )->execute([$result['message_id'], $provId, $qId]);

                $this->logEvent($item['newsletter_id'], $item['subscriber_id'], 'sent', $provId, $result['message_id']);



                $this->pdo->prepare(
                    "UPDATE mail_hub_newsletters SET total_sent = total_sent + 1, updated_at = NOW() WHERE id = ?"
                )->execute([$item['newsletter_id']]);

                $sentCount++;
            } else {
                $this->failQueueItem($qId, $item, $result['error'] ?? 'Unknown error');
                $failedCount++;
            }
        }

        $this->finaliseSentNewsletters();

        return [
            'success'   => true,
            'processed' => count($items),
            'sent'      => $sentCount,
            'failed'    => $failedCount,
        ];
    }

    public function getQueue(array $filters = []): array
    {
        $page   = max(1, (int)($filters['page'] ?? 1));
        $limit  = (int)($filters['limit'] ?? 50);
        $offset = ($page - 1) * $limit;
        $archive = !empty($filters['archive']);
        $table   = $archive ? 'mail_hub_queue_archive' : 'mail_hub_queue';

        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['newsletter_id'])) {
            $where[]  = "q.newsletter_id = :nlid";
            $params[':nlid'] = (int)$filters['newsletter_id'];
        }
        if (!empty($filters['status'])) {
            $where[]  = "q.status = :st";
            $params[':st'] = $filters['status'];
        }

        $sql = "SELECT SQL_CALC_FOUND_ROWS q.*, n.title AS newsletter_title, n.subject
                FROM `$table` q
                LEFT JOIN mail_hub_newsletters n ON n.id = q.newsletter_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY " . ($archive ? "q.archived_at DESC" : "q.priority DESC, q.id DESC") . "
                LIMIT :limit OFFSET :offset";

        $s = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $s->bindValue($k, $v);
        $s->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $s->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $s->execute();

        $rows  = $s->fetchAll(\PDO::FETCH_ASSOC);
        $total = (int)$this->pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

        return ['success' => true, 'rows' => $rows, 'total' => $total, 'pages' => max(1, ceil($total / $limit)), 'page' => $page];
    }

    public function archiveSentQueue(int $newsletterId): array
    {
        $this->pdo->beginTransaction();
        try {

            $this->pdo->prepare(
                "INSERT IGNORE INTO mail_hub_queue_archive
                    (id, newsletter_id, subscriber_id, status, priority, attempts, max_attempts,
                     provider_id, provider_msg_id, error_msg, scheduled_at, started_at, sent_at, created_at, archived_at)
                 SELECT id, newsletter_id, subscriber_id, status, priority, attempts, max_attempts,
                        provider_id, provider_msg_id, error_msg, scheduled_at, started_at, sent_at, created_at, NOW()
                 FROM mail_hub_queue
                 WHERE newsletter_id = ? AND status IN ('sent','failed','skipped')"
            )->execute([$newsletterId]);




            $del = $this->pdo->prepare(
                "DELETE FROM mail_hub_queue WHERE newsletter_id = ? AND status IN ('sent','failed','skipped')"
            );
            $del->execute([$newsletterId]);
            $count = $del->rowCount();

            $this->pdo->commit();
            return ['success' => true, 'count' => $count];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // SUBSCRIBERS & BREVO SYNC
    // ══════════════════════════════════════════════════════════════════════

    
    
    
    
    
    
    
        public function getSubscribers(array $filters = []): array
    {
        $page   = max(1, (int)($filters['page'] ?? 1));
        $limit  = (int)($filters['limit'] ?? 50);
        $offset = ($page - 1) * $limit;

        // Fetch live view directly from Brevo
        $data = $this->brevoApiGet("/contacts?limit={$limit}&offset={$offset}");
        if (!$data || !isset($data['contacts'])) {
            return ['success' => true, 'subscribers' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
        }
        
        $subs = [];
        foreach ($data['contacts'] as $c) {
            $subs[] = [
                'id'         => $c['id'],
                'email'      => $c['email'],
                'first_name' => $c['attributes']['FIRSTNAME'] ?? $c['attributes']['NAME'] ?? '',
                'last_name'  => $c['attributes']['LASTNAME'] ?? '',
                'status'     => !empty($c['emailBlacklisted']) ? 'unsubscribed' : 'active',
                'source'     => 'Brevo API',
                'created_at' => $c['createdAt'] ?? date('Y-m-d H:i:s'),
                'updated_at' => $c['modifiedAt'] ?? date('Y-m-d H:i:s'),
            ];
        }
        return [
            'success' => true, 
            'subscribers' => $subs, 
            'total' => $data['count'] ?? 0, 
            'pages' => max(1, ceil(($data['count'] ?? 0) / $limit)), 
            'page' => $page
        ];
    }

    public function unsubscribe(string $token): array
    {
        $s = $this->pdo->prepare(
            "SELECT * FROM mail_hub_unsubscribe_tokens WHERE token = ? AND used = 0 LIMIT 1"
        );
        $s->execute([$token]);
        $row = $s->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'error' => 'Invalid or already-used unsubscribe link'];
        }

        $this->brevoApiPut("/contacts/{$row['subscriber_id']}", ['emailBlacklisted' => true]);

        $this->pdo->prepare(
            "UPDATE mail_hub_unsubscribe_tokens SET used = 1, used_at = NOW() WHERE token = ?"
        )->execute([$token]);

        if (!empty($row['newsletter_id'])) {
            $this->logEvent($row['newsletter_id'], $row['subscriber_id'], 'unsubscribed');
        }

        return ['success' => true];
    }





    
    
    
    
    
    
    
    
    
    
    
    
    
    
    

    // ══════════════════════════════════════════════════════════════════════
    // LISTS
    // ══════════════════════════════════════════════════════════════════════

    public function getLists(): array
    {
        return $this->pdo->query(
            "SELECT l.*,
                (SELECT COUNT(*) FROM mail_hub_list_subscribers ls WHERE ls.list_id = l.id) AS subscriber_count
             FROM mail_hub_lists l ORDER BY l.is_default DESC, l.name ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════════════════════
    // DASHBOARD STATS
    // ══════════════════════════════════════════════════════════════════════

    public function getDashboardStats(): array
    {
        $nlCounts = $this->pdo->query(
            "SELECT status, COUNT(*) AS cnt FROM mail_hub_newsletters GROUP BY status"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
        
        
        
        
        
        
        $brevoData = $this->brevoApiGet("/contacts?limit=1&offset=0");
        $totalSubs = $brevoData['count'] ?? 0;
        
        $totalQueued = (int)$this->pdo->query("SELECT COUNT(*) FROM mail_hub_queue WHERE status = 'pending'")->fetchColumn();



        
        
        
        
        
        
        
        
        $recentSent = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM mail_hub_queue WHERE status = 'sent' AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetchColumn();

        $recentNl = $this->pdo->query(
            "SELECT id, title, status, total_sent, total_recipients, sent_at
             FROM mail_hub_newsletters ORDER BY created_at DESC LIMIT 5"
        )->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'success'           => true,
            'newsletters'       => $nlCounts,
            'active_subscribers'=> $totalSubs,
            'pending_queue'     => $totalQueued,
            'sent_last_7d'      => $recentSent,
            'recent_newsletters'=> $recentNl,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // INTERNAL HELPERS
    // ══════════════════════════════════════════════════════════════════════


    private function resolveRecipients(?int $listId): array
    {
        $limit = 500;
        $offset = 0;
        $allIds = [];
        do {
            $endpoint = $listId 
                ? "/contacts/lists/{$listId}/contacts?limit={$limit}&offset={$offset}"
                : "/contacts?limit={$limit}&offset={$offset}";
                
            $data = $this->brevoApiGet($endpoint);
            $contacts = $data['contacts'] ?? [];
            
            foreach ($contacts as $c) {
                if (empty($c['emailBlacklisted'])) {
                    $allIds[] = ['id' => $c['id']];
                }
            }
            $offset += $limit;
        } while (count($contacts) === $limit);
        
        return $allIds;
    }



    private function failQueueItem(int $qId, array $item, string $error): void
    {
        $attempts = (int)$item['attempts'] + 1;
        $maxAtt   = (int)($item['max_attempts'] ?? 3);
        $newStatus = $attempts >= $maxAtt ? 'failed' : 'pending';

        $this->pdo->prepare(
            "UPDATE mail_hub_queue
             SET status = ?, attempts = ?, error_msg = ?, completed_at = IF(? = 'failed', NOW(), NULL)
             WHERE id = ?"
        )->execute([$newStatus, $attempts, $error, $newStatus, $qId]);

        
        
        
        

        if ($newStatus === 'failed') {
            $this->pdo->prepare(
                "UPDATE mail_hub_newsletters SET total_failed = total_failed + 1 WHERE id = ?"
            )->execute([$item['newsletter_id']]);
            $this->logEvent($item['newsletter_id'], $item['subscriber_id'], 'failed', null, null, ['error' => $error]);
        }


        
        
        
    }

    private function finaliseSentNewsletters(): void
    {
        $this->pdo->exec(
            "UPDATE mail_hub_newsletters n
             SET n.status = 'sent', n.sent_at = NOW()
             WHERE n.status = 'sending'
               AND NOT EXISTS (
                   SELECT 1 FROM mail_hub_queue q
                   WHERE q.newsletter_id = n.id
                     AND q.status IN ('pending','processing')
               )"
        );
    }

    private function checkAndIncrementDailyLimit(array $provRow): bool
    {
        if (empty($provRow['daily_limit'])) return true;

        $today = date('Y-m-d');
        if (($provRow['last_reset'] ?? '') !== $today) {
            $this->pdo->prepare(
                "UPDATE mail_hub_providers SET sent_today = 0, last_reset = ? WHERE id = ?"
            )->execute([$today, $provRow['id']]);
            $provRow['sent_today'] = 0;
        }

        if ((int)$provRow['sent_today'] >= (int)$provRow['daily_limit']) return false;

        $this->pdo->prepare(
            "UPDATE mail_hub_providers SET sent_today = sent_today + 1 WHERE id = ?"
        )->execute([$provRow['id']]);
        return true;
    }

    private function ensureUnsubToken(int $subscriberId, int $newsletterId): string
    {
        $s = $this->pdo->prepare(
            "SELECT token FROM mail_hub_unsubscribe_tokens
             WHERE subscriber_id = ? AND newsletter_id = ? AND used = 0 LIMIT 1"
        );
        $s->execute([$subscriberId, $newsletterId]);
        $existing = $s->fetchColumn();
        if ($existing) return $existing;

        $token = bin2hex(random_bytes(32));
        $this->pdo->prepare(
            "INSERT INTO mail_hub_unsubscribe_tokens (token, subscriber_id, newsletter_id) VALUES (?, ?, ?)"
        )->execute([$token, $subscriberId, $newsletterId]);
        return $token;
    }

    private function buildUnsubscribeUrl(string $token): string
    {
        $base = getenv('SITE_BASE_URL') ?: 'http://localhost';
        return rtrim($base, '/') . '/mail_hub/unsubscribe.php?token=' . $token;
    }

    private function injectUnsubscribeLink(string $html, string $url): string
    {
        $link = '<p style="font-size:11px;color:#999;text-align:center;margin-top:24px;">'
              . '<a href="' . htmlspecialchars($url) . '" style="color:#999;">Unsubscribe</a></p>';

        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $link . '</body>', $html);
        }
        return $html . $link;
    }

    private function logEvent(
        int $newsletterId, int $subscriberId,
        string $type, ?int $providerId = null, ?string $msgId = null, array $meta = []
    ): void {
        $this->pdo->prepare(
            "INSERT INTO mail_hub_events
                (newsletter_id, subscriber_id, event_type, provider_id, provider_msg_id, metadata)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $newsletterId, $subscriberId, $type, $providerId, $msgId,
            !empty($meta) ? json_encode($meta) : null,
        ]);
    }

    private function sanitiseJson(string $raw): string
    {
        $raw = trim($raw);
        if (empty($raw)) return '{}';
        return json_decode($raw) !== null ? $raw : '{}';
    }

    // ── Zero-PII Brevo Helpers ────────────────────────────────────────────────────────
    
    private function getBrevoKey(): ?string {
        return $this->pdo->query("SELECT JSON_UNQUOTE(JSON_EXTRACT(config, '$.api_key')) FROM mail_hub_providers WHERE driver = 'brevo' AND is_enabled = 1 ORDER BY is_default DESC LIMIT 1")->fetchColumn() ?: null;
    }

    private function brevoApiGet(string $endpoint): ?array {
        if (!($apiKey = $this->getBrevoKey())) return null;
        $ch = curl_init('https://api.brevo.com/v3' . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['api-key: ' . $apiKey, 'Accept: application/json']
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res ? json_decode($res, true) : null;
    }

    private function brevoApiPut(string $endpoint, array $payload): ?array {
        if (!($apiKey = $this->getBrevoKey())) return null;
        $ch = curl_init('https://api.brevo.com/v3' . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['api-key: ' . $apiKey, 'Content-Type: application/json', 'Accept: application/json']
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res ? json_decode($res, true) : null;
    }
}

