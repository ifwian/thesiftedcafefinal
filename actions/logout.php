<?php
// STAFF LOG OUT: clears the session, then goes back to the home page.
require dirname(__DIR__) . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid($_POST['csrf'] ?? null)) {
    sign_out();
}
header('Location: ../index.html');
exit;
