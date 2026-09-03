<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/lib/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$params = request_params();
$question = trim((string) ($params['question'] ?? ''));

if ($question === '' || mb_strlen($question) > 1000) {
    json_error('Please provide a question under 1000 characters.');
}

$apiKey = env('ANTHROPIC_API_KEY', '');
if (!$apiKey) {
    json_error('Server is missing ANTHROPIC_API_KEY configuration.', 500);
}
$model = env('ANTHROPIC_MODEL', 'claude-sonnet-4-5-20250929');

// --- Give the assistant real context instead of letting it guess -------
$pdo = Database::connection();
$totalRow  = $pdo->query('SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count FROM donations')->fetch();
$byPurpose = $pdo->query(
    'SELECT purpose, SUM(amount) AS total FROM donations GROUP BY purpose ORDER BY total DESC'
)->fetchAll();
$recent = $pdo->query(
    'SELECT donor_name, amount, purpose, created_at FROM donations ORDER BY created_at DESC LIMIT 10'
)->fetchAll();

$context = json_encode([
    'total_amount_zar' => (float) $totalRow['total'],
    'donation_count'   => (int) $totalRow['count'],
    'by_purpose'       => $byPurpose,
    'recent_donations' => $recent,
], JSON_PRETTY_PRINT);

$systemPrompt = <<<SYS
You are the donation assistant embedded in the AI Donation Tracker app.
Answer questions ONLY using the donation data provided below. Amounts are
in South African Rand (R) unless the user asks specifically about Cardano
(ADA) donations. If the data doesn't contain the answer, say so plainly
rather than guessing. Keep answers short (2-4 sentences).

Donation data:
{$context}
SYS;

$payload = [
    'model'      => $model,
    'max_tokens' => 400,
    'system'     => $systemPrompt,
    'messages'   => [
        ['role' => 'user', 'content' => $question],
    ],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($body === false) {
    error_log("[Claude API] cURL error: {$curlError}");
    json_error('Could not reach the AI assistant right now.', 502);
}

$decoded = json_decode($body, true);

if ($status >= 400 || !is_array($decoded)) {
    error_log("[Claude API] HTTP {$status}: {$body}");
    json_error('The AI assistant returned an error.', 502);
}

$answer = '';
foreach ($decoded['content'] ?? [] as $block) {
    if (($block['type'] ?? '') === 'text') {
        $answer .= $block['text'];
    }
}
$answer = trim($answer) ?: 'Sorry, I couldn\'t generate a response.';

// Log the exchange (best-effort — don't fail the request if this errors)
try {
    $pdo->prepare('INSERT INTO ai_queries (question, answer) VALUES (:q, :a)')
        ->execute([':q' => $question, ':a' => $answer]);
} catch (Throwable $e) {
    error_log('[ai_queries] insert failed: ' . $e->getMessage());
}

json_response(['ok' => true, 'answer' => $answer]);
