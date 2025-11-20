<?php
require_once __DIR__ . '/../../includes/db.php';

$id = $_GET['id'] ?? 0;
$uploaded_by = $_GET['uploaded_by'] ?? 0;

$stmt = $conn->prepare("SELECT file_name FROM attachment_files WHERE id = ? AND uploaded_by_id = ?");
$stmt->bind_param("ii", $id, $uploaded_by);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$file) {
    echo "<p class='text-muted'>No attachment provided.</p>";
    exit;
}

$filename = htmlspecialchars($file['file_name']);
$path = "../uploads/remarks/" . $filename;
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

/* ✅ IMAGE PREVIEW (mobile-friendly) */
if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
    echo "
        <div style='width:100%; max-height:70vh; overflow:auto; text-align:center;'>
            <img src='{$path}' 
                 style='max-width:100%; height:auto; object-fit:contain;' 
                 class='rounded shadow-sm'>
        </div>
    ";
    exit;
}

/* ✅ PDF PREVIEW  */
if ($ext === 'pdf') {

    echo "
        <div class='text-center p-3'>
            <i class='bi bi-file-earmark-pdf' style='font-size: 60px; color:#d9534f;'></i>
            <h5 class='mt-3'>PDF File</h5>
            <p class='text-muted'>Your device does not allow PDF to be viewed inside this window.</p>
            
            <a href='{$path}' 
               class='btn btn-primary mt-2' 
               target='_blank'>
               Open PDF
            </a>
        </div>
    ";
    exit;
}

/* Unsupported file */
echo "<p class='text-muted'>File format not supported for preview.</p>";
?>
