<?php
session_start();
if (!isset($_SESSION['test'])) {
    $_SESSION['test'] = 'Session is working!';
    $msg = 'Session variable set. Refresh this page.';
} else {
    $msg = 'Session variable: ' . $_SESSION['test'];
}
echo "<h2>$msg</h2>";
?>
