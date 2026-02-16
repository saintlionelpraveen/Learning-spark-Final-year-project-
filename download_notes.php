<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes = $_POST['notes'] ?? '';
    $content_id = $_POST['content_id'] ?? '';
    $title = $_POST['title'] ?? 'Untitled';

    // Sanitize the title for the filename
    $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $title) . '_notes.txt';

    // Set headers to force download
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($notes));

    // Output the notes content
    echo $notes;
    exit();
} else {
    // If accessed directly, redirect back
    header("Location: staff_content.php?staff_id=" . intval($_GET['staff_id'] ?? 0));
    exit();
}
?>