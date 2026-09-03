<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/lib/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed.', 405);
}

$pdo = Database::connection();

$limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 20;

$rows = $pdo->prepare(
    'SELECT id, donor_name, amount, purpose, cardano_tx_hash, verified, created_at
     FROM donations
     ORDER BY created_at DESC
     LIMIT :limit'
);
$rows->bindValue(':limit', $limit, PDO::PARAM_INT);
$rows->execute();

$totalRow = $pdo->query('SELECT COALESCE(SUM(amount), 0) AS total FROM donations')->fetch();

$donations = array_map(static function (array $row): array {
    return [
        'id'        => (int) $row['id'],
        'donorName' => $row['donor_name'],
        'amount'    => (float) $row['amount'],
        'purpose'   => $row['purpose'],
        'txHash'    => $row['cardano_tx_hash'],
        'verified'  => (bool) $row['verified'],
        'date'      => date('d/m/Y', strtotime($row['created_at'])),
    ];
}, $rows->fetchAll());

json_response([
    'ok'        => true,
    'total'     => (float) $totalRow['total'],
    'donations' => $donations,
]);
