<?php
/*
 * Shows an uploaded payment proof to STAFF ONLY.
 * The screenshots are saved in storage/proofs/, a folder the browser cannot open
 * directly, so the only way to see a proof is through this page (which checks the role).
 */
require __DIR__ . '/includes/bootstrap.php';
$user = current_user();
if ($user === null || $user['role'] !== 'staff') {
    http_response_code(403);
    exit('Staff only.');
}
$stmt = db()->prepare('SELECT proof_file, proof_mime, proof_original_name FROM payments WHERE reservation_id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$p = $stmt->fetch();
$file = $p ? cfg('proof_dir') . '/' . basename((string) $p['proof_file']) : '';
if (!$p || !$p['proof_file'] || !is_file($file) || !isset(cfg('proof_types')[$p['proof_mime']])) {
    http_response_code(404);
    exit('No proof found.');
}
header('Content-Type: ' . $p['proof_mime']);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="proof.' . cfg('proof_types')[$p['proof_mime']] . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");
header('Cache-Control: private, no-store');
readfile($file);
