<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Optional: Add role-based access control
function checkPermission($requiredRole) {
    if ($_SESSION['role'] !== $requiredRole) {
        header("Location: /unauthorized.php");
        exit();
    }
}