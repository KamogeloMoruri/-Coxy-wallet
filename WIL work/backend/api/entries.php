<?php
// ==========================================================
// GET /api/entries.php
// Returns entries for the active contest round, highest voted first.
// Vote counts are computed from the votes table rather than stored
// redundantly on entries.
// ==========================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = get_db_connection();
    $contestId = get_active_contest_id($pdo);

    if (!$contestId) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT
            e.id,
            e.title,
            e.description,
            e.ai_tool_used,
            e.audio_url,
            u.username AS artist,
            COUNT(v.id) AS vote_count
         FROM entries e
         JOIN users u ON u.id = e.user_id
         LEFT JOIN votes v ON v.entry_id = e.id
         WHERE e.contest_id = ?
         GROUP BY e.id
         ORDER BY vote_count DESC, e.created_at ASC'
    );
    $stmt->execute([$contestId]);
    echo json_encode($stmt->fetchAll());
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error fetching entries']);
    error_log($e->getMessage());
}
