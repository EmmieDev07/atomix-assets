<?php
require __DIR__ . '/../config/database.php';
$db = Database::getInstance()->getConnection();
$q = $db->query('SELECT class_id, class_name FROM classes LIMIT 1');
$r = $q->fetch();
if ($r) {
    echo $r['class_id'] . '|' . $r['class_name'];
} else {
    echo 'NO_CLASS';
}
