<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

checkTeacherAuth();
echo json_encode([
    'user_id' => $_SESSION['user_id'] ?? null,
    'role' => $_SESSION['role'] ?? null,
    'teacher_id' => $_SESSION['teacher_id'] ?? null,
    'name' => $_SESSION['name'] ?? null,
    'email' => $_SESSION['email'] ?? null
]);
