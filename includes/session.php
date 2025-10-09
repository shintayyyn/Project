<?php
session_start();

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

// Function to check if user is a teacher
function isTeacher() {
    return isLoggedIn() && $_SESSION['user_type'] === 'teacher';
}

// Function to check if user is an admin
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_type'] === 'admin';
}

function isDean() {
    return isLoggedIn() && $_SESSION['user_type'] === 'dean';
}

// Function to get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Function to get current user type
function getCurrentUserType() {
    return $_SESSION['user_type'] ?? null;
}

// Function to require teacher authentication
function requireTeacher() {
    if (!isTeacher()) {
        header('Location: ../../login.php');
        exit();
    }
}

// Function to require admin authentication
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ../../login.php');
        exit();
    }
}

function requireDean() {
    if (!isDean()) {
        header('Location: ../../login.php');
        exit();
    }
}



