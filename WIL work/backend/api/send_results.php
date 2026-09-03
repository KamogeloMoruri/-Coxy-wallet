<?php
// ==========================================================
// Run at the end of a contest round to email every entrant their
// result. Intended to run from the command line or a cron job:
//   php api/send_results.php
// ==========================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

$pdo = get_db_connection();
$contestId = get_active_contest_id($pdo);

if (!$contestId) {
    echo "No active contest round found.\n";
    exit;
}

$stmt = $pdo->prepare(
    'SELECT
        e.title,
        u.email,
        COUNT(v.id) AS vote_count
     FROM entries e
     JOIN users u ON u.id = e.user_id
     LEFT JOIN votes v ON v.entry_id = e.id
     WHERE e.contest_id = ? AND u.email IS NOT NULL AND u.email != ""
     GROUP BY e.id
     ORDER BY vote_count DESC'
);
$stmt->execute([$contestId]);
$entries = $stmt->fetchAll();

if (!$entries) {
    echo "No entries with an email on file.\n";
    exit;
}

$topVotes = $entries[0]['vote_count'];

foreach ($entries as $entry) {
    $isWinner = (int)$entry['vote_count'] === (int)$topVotes;
    send_contest_results_email($entry['email'], $entry['title'], (int)$entry['vote_count'], $isWinner);
    echo "Emailed {$entry['email']} about \"{$entry['title']}\"\n";
}

// Optionally close out the round so a new one can be marked active
$pdo->prepare("UPDATE contests SET status = 'closed' WHERE id = ?")->execute([$contestId]);

echo "Done.\n";
