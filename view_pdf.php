<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['content_id'])) {
    die("Content ID not provided.");
}

$content_id = intval($_GET['content_id']);
$download = isset($_GET['download']) && $_GET['download'] == 1;

$conn = new mysqli("localhost", "root", "", "learning");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT file_path FROM staff_content WHERE content_id = ?");
$stmt->bind_param("i", $content_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$result) {
    die("Content not found.");
}

$file_path = $result['file_path'];
// For simplicity, assume all documents are stored as PDFs or converted on upload
// In a real scenario, you'd convert DOCX, PPTX, etc., to PDF here using a library like FPDF or wkhtmltopdf

if (!file_exists($file_path)) {
    die("File not found.");
}

header('Content-Type: application/pdf');
if ($download) {
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
} else {
    header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
}
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit();