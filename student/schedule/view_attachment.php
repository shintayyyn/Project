<?php
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_GET['id'], $_GET['ss_id'], $_GET['uploaded_by_id'])) {
    echo "Invalid request.";
    exit;
}

$attachment_id = (int) $_GET['id'];
$ss_id = (int) $_GET['ss_id'];
$uploaded_by = (int) $_GET['uploaded_by'];

// Fetch attachment using all three
$stmt = $conn->prepare("
    SELECT file_name, file_type, file_data 
    FROM attachment_files 
    WHERE id = ? AND ss_id = ? AND uploaded_by_id = ? AND uploaded_by_type = 'teacher'
    LIMIT 1
");
$stmt->bind_param("iii", $attachment_id, $ss_id, $uploaded_by);
$stmt->execute();
$result = $stmt->get_result();
$attachment = $result->fetch_assoc();
$stmt->close();

if (!$attachment) {
    echo "Attachment not found.";
    exit;
}

$file_type = $attachment['file_type'];
$file_name = $attachment['file_name'];
$file_data = $attachment['file_data'];

if (strpos($file_type, 'image') !== false) {
    echo '<img src="data:'.$file_type.';base64,'.base64_encode($file_data).'" class="img-fluid" />';
} elseif (strpos($file_type, 'pdf') !== false) {
    echo '<iframe src="data:'.$file_type.';base64,'.base64_encode($file_data).'" style="width:100%;height:500px;" frameborder="0"></iframe>';
} else {
    echo '<p><strong>'.$file_name.'</strong></p>';
    echo '<a href="data:'.$file_type.';base64,'.base64_encode($file_data).'" download="'.$file_name.'" class="btn btn-primary">Download File</a>';
}
?>
