<?php
/**
 * SAGE Mail Hub — API
 * public/mail_hub/api.php
 *
 * All actions POST JSON; returns JSON { ok, data, error }.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../env_locals.php';
require_once PROJECT_ROOT . '/src/MailHub/MailHubManager.php';
require_once PROJECT_ROOT . '/src/MailHub/Providers/MailProviderInterface.php';
require_once PROJECT_ROOT . '/src/MailHub/Providers/BrevoProvider.php';
require_once PROJECT_ROOT . '/src/MailHub/Providers/SmtpProvider.php';

use App\MailHub\MailHubManager;

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit;
}

function ok($data = null)  { echo json_encode(['ok' => true,  'data'  => $data]);  exit; }
function fail(string $msg) { echo json_encode(['ok' => false, 'error' => $msg]);   exit; }

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed');

    $raw = file_get_contents('php://input');
    $req = json_decode($raw, true);
    if (!$req || empty($req['action'])) fail('Missing action');

    global $pdo;
    $hub    = new MailHubManager($pdo);
    $action = $req['action'];

    switch ($action) {

        // ── DASHBOARD ─────────────────────────────────────────────────────

        case 'get_stats':
            ok($hub->getDashboardStats());

        // ── NEWSLETTERS ───────────────────────────────────────────────────

        case 'list_newsletters':
            ok($hub->getNewsletters([
                'status' => $req['status'] ?? '',
                'search' => $req['search'] ?? '',
                'page'   => (int)($req['page'] ?? 1),
                'limit'  => (int)($req['limit'] ?? 40),
            ]));

        case 'get_newsletter':
            $nl = $hub->getNewsletterById((int)($req['id'] ?? 0));
            if (!$nl) fail('Not found');
            ok($nl);

        case 'save_newsletter':
            $result = $hub->saveNewsletter($req['newsletter'] ?? []);
            if (!$result['success']) fail($result['error'] ?? 'Save failed');
            ok($result);

        case 'delete_newsletter':
            $result = $hub->deleteNewsletter((int)($req['id'] ?? 0));
            if (!$result['success']) fail($result['error'] ?? 'Delete failed');
            ok($result);

        case 'duplicate_newsletter':
            $result = $hub->duplicateNewsletter((int)($req['id'] ?? 0));
            if (!$result['success']) fail($result['error'] ?? 'Duplicate failed');
            ok($result);

        case 'enqueue_newsletter':
            $result = $hub->enqueueNewsletter((int)($req['id'] ?? 0));
            if (!$result['success']) fail($result['error'] ?? 'Enqueue failed');
            ok($result);

        // ── TEMPLATES ─────────────────────────────────────────────────────

        case 'list_templates':
            ok($hub->getTemplates());

        case 'get_template':
            $tpl = $hub->getTemplateById((int)($req['id'] ?? 0));
            if (!$tpl) fail('Template not found');
            ok($tpl);

        case 'save_template':
            $result = $hub->saveTemplate($req['template'] ?? []);
            if (!$result['success']) fail($result['error'] ?? 'Save failed');
            ok($result);

        case 'delete_template':
            ok($hub->deleteTemplate((int)($req['id'] ?? 0)));

        // ── QUEUE ─────────────────────────────────────────────────────────



        case 'get_queue':
            ok($hub->getQueue([
                'page'          => (int)($req['page'] ?? 1),
                'limit'         => (int)($req['limit'] ?? 50),
                'archive'       => !empty($req['archive']),
                'newsletter_id' => !empty($req['newsletter_id']) ? (int)$req['newsletter_id'] : null,
                'status'        => $req['status'] ?? '',
            ]));

        case 'archive_queue':
            $result = $hub->archiveSentQueue((int)($req['newsletter_id'] ?? 0));
            if (!$result['success']) fail($result['error'] ?? 'Archive failed');
            ok($result);

        case 'process_batch':
            $result = $hub->processBatch(
                (int)($req['batch_size']    ?? 20),
                !empty($req['newsletter_id']) ? (int)$req['newsletter_id'] : null
            );
            ok($result);

        // ── SUBSCRIBERS ───────────────────────────────────────────────────

        case 'list_subscribers':
            ok($hub->getSubscribers([
                'status'  => $req['status']  ?? '',
                'search'  => $req['search']  ?? '',
                'page'    => (int)($req['page'] ?? 1),
                'limit'   => (int)($req['limit'] ?? 50),
            ]));
            


        // ── LISTS ─────────────────────────────────────────────────────────

        case 'list_lists':
            ok($hub->getLists());

        // ── PROVIDERS ─────────────────────────────────────────────────────

        case 'list_providers':
            ok(['providers' => $hub->getProviders(), 'drivers' => $hub->getDriverOptions()]);

        case 'save_provider':
            $result = $hub->saveProvider($req['provider'] ?? []);
            if (!$result['success']) fail($result['error'] ?? 'Save failed');
            ok($result);

        case 'delete_provider':
            $result = $hub->deleteProvider((int)($req['id'] ?? 0));
            ok($result);

        case 'test_provider':
            $provId  = (int)($req['provider_id'] ?? 0);
            $to      = trim($req['to_email'] ?? '');
            if (!$provId || !$to) fail('provider_id and to_email required');
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) fail('Invalid email');
            $provRow = $hub->getProviderById($provId);
            if (!$provRow) fail('Provider not found');
            $driver = $hub->getProvider($provRow);
            $result = $driver->send([
                'to_email'   => $to,
                'to_name'    => null,
                'from_email' => $provRow['config']['default_from'] ?? $to,
                'from_name'  => $provRow['config']['default_name'] ?? 'SAGE Mail Hub',
                'reply_to'   => null,
                'subject'    => '[SAGE Mail Hub] Provider test',
                'html'       => '<p>This is a test email from <strong>SAGE Mail Hub</strong>. Provider is working correctly.</p>',
                'text'       => 'SAGE Mail Hub provider test — everything is working.',
            ]);
            ok($result);

        default:
            fail('Unknown action: ' . htmlspecialchars($action));
    }

} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}




