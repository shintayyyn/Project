<?php
require_once __DIR__ . '/../includes/db.php';
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $fileTmp  = $_FILES['profile_picture']['tmp_name'];
    $fileName = basename($_FILES['profile_picture']['name']);
    $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Allow only images
    $allowedExt = ['jpg','jpeg','png','gif'];
    if (!in_array($fileExt, $allowedExt)) {
        die("Invalid file type.");
    }

    // Save to filesystem
    $uploadDir  = __DIR__ . "/uploads/students/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $newFileName = uniqid() . "." . $fileExt;
    $filePath = $uploadDir . $newFileName;
    move_uploaded_file($fileTmp, $filePath);

    // Store relative path
    $relativePath = "uploads/students/" . $newFileName;

    // Store binary blob
    $blob = file_get_contents($filePath);

    // Save both into DB
    $stmt = $conn->prepare("UPDATE students SET profile_path=?, profile_blob=? WHERE s_id=?");
    $stmt->bind_param("ssi", $relativePath, $blob, $studentId);
    $stmt->send_long_data(1, $blob); // important for LONGBLOB
    $stmt->execute();
}
?>