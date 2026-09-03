<?php
/**
 * config.php
 * Loaded by every endpoint. Reads secrets from `.env`, never from source
 * control, and sets baseline security headers before any handler runs.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Load .env (simple parser — no Composer dependency required)
// ---------------------------------------------------------------------
function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key   = trim($key);
        $value = trim($value);
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

load_env(__DIR__ . '/.env');

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

// ---------------------------------------------------------------------
// Security headers & CORS
// ---------------------------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer-when-downgrade');

$allowedOrigins = array_map('trim', explode(',', env('ALLOWED_ORIGINS', '') ?? ''));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------------------------------------------------------------------
// Helpers shared by every endpoint
// ---------------------------------------------------------------------
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    json_response(['ok' => false, 'error' => $message], $status);
}

/** Reads and JSON-decodes the request body, or [] if empty/invalid. */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * A request may arrive as JSON (fetch with a JSON body) or as a normal
 * form POST. This normalizes both into one associative array so endpoint
 * code doesn't need to care which one the client used.
 */
function request_params(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_starts_with($contentType, 'application/json')) {
        return read_json_body();
    }
    return $_POST;
}
