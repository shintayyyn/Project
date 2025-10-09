<?php
session_start();

$role = $_POST['role'] ?? 'teacher';

if (isset($_SESSION['t_id']) && isset($_SESSION['is_dean']) && $_SESSION['is_dean'] === true) {
    $_SESSION['user_type'] = $role;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Unauthorized']);
}
