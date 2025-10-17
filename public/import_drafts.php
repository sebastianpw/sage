<?php require_once __DIR__ . '/bootstrap.php'; require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;
use App\Entity\ChatSession;
use App\Entity\ChatMessage;

// --- CONFIG ---
$userId = 1;
$draftsFolder = __DIR__ . '/drafts'; // Adjust path as needed

$spw = SpwBase::getInstance();
$em = $spw->getEntityManager();

// --- CREATE NEW CHAT SESSION ---
$sessionId = bin2hex(random_bytes(16));
$session = new ChatSession();
$session->setSessionId($sessionId);
$session->setUser($em->getRepository(\App\Entity\User::class)->find($userId));
$session->setTitle("Draft Chat " . date('Y-m-d H:i:s'));

$em->persist($session);
$em->flush();

echo "Created new chat session with ID: $sessionId\n";

// --- READ DRAFT FILES ---
$files = glob($draftsFolder . '/*.txt');

// Sort files numerically based on filename
usort($files, function($a, $b) {
    return intval(basename($a, '.txt')) - intval(basename($b, '.txt'));
});

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) continue;

    $message = new ChatMessage();
    $message->setSession($session);
    $message->setRole('user'); // or 'assistant', depending on your draft
    $message->setContent($content);
    $message->setCreatedAt(new \DateTimeImmutable());

    $em->persist($message);

    echo "Added message from file: " . basename($file) . "\n";
}

// --- SAVE ALL MESSAGES ---
$em->flush();

echo "All messages inserted successfully.\n";



