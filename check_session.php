<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    echo json_encode(['logged_in' => true, 'username' => $_SESSION['admin_username']]);
} else {
    echo json_encode(['logged_in' => false]);
}
?>