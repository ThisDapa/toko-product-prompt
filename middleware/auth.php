<?php

function checkAuth() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: /auth/login');
        exit();
    }
}

function checkAdmin() {
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        echo "403 - Access Forbidden";
        exit();
    }
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}