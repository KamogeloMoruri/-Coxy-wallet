<?php
// ==========================================================
// POST /api/upload_entry.php  (multipart/form-data)
// Fields: transaction_id, title, ai_tool, description, email, audio (file)
//
// Only accepts an entry if the linked transaction is confirmed
// and hasn't already been used for an entry.
// ==========================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';
$config = require __DIR__ . '/../config/config.php';

try {
    $pdo = get_db_connection();

    $transactionId = (int)($_POST['transaction_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $aiTool      = trim($_POST['ai_tool'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $email       = trim($_POST['email'] ?? '');

    if (!$transactionId || $title === '' || empty($_FILES['audio'])) {
        http_response_code(400);
        echo json_encode(['error' => 'transaction_id, title and an audio file are required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'confirmed'");
    $stmt->execute([$transactionId]);
    $txRow = $stmt->fetch();

    if (!$txRow) {
        http_response_code(402); // Payment Required
        echo json_encode(['error' => 'Payment not confirmed yet']);
        exit;
    }

    if (!empty($txRow['entry_id'])) {
        http_response_code(409);
        echo json_encode(['error' => 'This payment has already been used for an entry']);
        exit;
    }

    $contestId = get_active_contest_id($pdo);
    if (!$contestId) {
        http_response_code(409);
        echo json_encode(['error' => 'No active contest round right now']);
        exit;
    }

    $file = $_FILES['audio'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'File upload failed']);
        exit;
    }
    if ($file['size'] > $config['max_upload_bytes']) {
        http_response_code(400);
        echo json_encode(['error' => 'File is too large']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $config['allowed_audio_types'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported audio format']);
        exit;
    }

    if (!is_dir($config['upload_dir'])) {
        mkdir($config['upload_dir'], 0755, true);
    }

    $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($file['name'], PATHINFO_EXTENSION));
    $storedName = bin2hex(random_bytes(16)) . '.' . $safeExt;
    $destPath = $config['upload_dir'] . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not save the uploaded file']);
        exit;
    }
    $audioUrl = 'uploads/audio/' . $storedName;

    if ($email !== '') {
        $pdo->prepare('UPDATE users SET email = ? WHERE id = ? AND (email IS NULL OR email = "")')
            ->execute([$email, $txRow['user_id']]);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO entries (contest_id, user_id, title, audio_url, description, ai_tool_used)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$contestId, $txRow['user_id'], $title, $audioUrl, $description, $aiTool]);
    $entryId = $pdo->lastInsertId();

    $pdo->prepare('UPDATE transactions SET entry_id = ? WHERE id = ?')
        ->execute([$entryId, $transactionId]);

    $pdo->commit();

    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$txRow['user_id']]);
    $user = $stmt->fetch();
    if (!empty($user['email'])) {
        send_entry_confirmation_email($user['email'], $title);
    }

    echo json_encode(['entry_id' => (int)$entryId, 'status' => 'submitted']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Server error submitting entry']);
    error_log($e->getMessage());
}
