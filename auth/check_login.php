<?php
// Bắt đầu session nếu chưa bắt đầu
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra xem người dùng đã đăng nhập chưa
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Kiểm tra xem người dùng có quyền quản lý không
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'quản lý';
}

// Chuyển hướng người dùng nếu chưa đăng nhập
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /quanlykhachsan/auth/login.php');
        exit();
    }
}

// Chuyển hướng người dùng nếu không phải quản lý
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /quanlykhachsan/index.php?error=permission');
        exit();
    }
}