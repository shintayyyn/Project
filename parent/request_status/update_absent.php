<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../../includes/db.php');
header('Content-Type: application/json');

if(!isset($_SESSION['parent_id'])){
    echo json_encode(['status'=>'error','message'=>'Not logged in']);
    exit();
}

$parent_id = $_SESSION['parent_id'];
$request_id = $_POST['request_id'] ?? null;
$absent_date = $_POST['absent_date'] ?? null;
$reason = trim($_POST['reason'] ?? '');

if(!$request_id || !$absent_date || !$reason){
    echo json_encode(['status'=>'error','message'=>'All fields are required']);
    exit();
}

// Check ownership
$stmt = $conn->prepare("SELECT * FROM absent_requests WHERE id=? AND p_id=? AND status='Pending'");
$stmt->bind_param("ii", $request_id, $parent_id);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows === 0){
    echo json_encode(['status'=>'error','message'=>'Request not found or cannot be edited']);
    exit();
}

$current = $result->fetch_assoc();

// Handle optional attachment
$attachment_path = null;
$attachment_changed = false;
if(isset($_FILES['attachment']) && $_FILES['attachment']['error']===UPLOAD_ERR_OK){
    $upload_dir = '../uploads/absent_attachments/';
    if(!is_dir($upload_dir)) mkdir($upload_dir,0777,true);

    $file_name = time().'_'.basename($_FILES['attachment']['name']);
    $target_file = $upload_dir.$file_name;

    if(move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)){
        $attachment_path = $file_name; // store only filename
        $attachment_changed = true;
    } else {
        echo json_encode(['status'=>'error','message'=>'Failed to upload attachment']);
        exit();
    }
}

// Check if any changes were made
if($absent_date === $current['absent_date'] &&
   $reason === $current['reason'] &&
   !$attachment_changed){
    echo json_encode(['status'=>'primary','message'=>'No changes were made']);
    exit();
}

// Update record
if($attachment_path){
    $stmt = $conn->prepare("UPDATE absent_requests SET absent_date=?, reason=?, attachment=? WHERE id=?");
    $stmt->bind_param("sssi",$absent_date,$reason,$attachment_path,$request_id);
} else {
    $stmt = $conn->prepare("UPDATE absent_requests SET absent_date=?, reason=? WHERE id=?");
    $stmt->bind_param("ssi",$absent_date,$reason,$request_id);
}

if(!$stmt->execute()){
    echo json_encode(['status'=>'error','message'=>'Database error: '.$stmt->error]);
    exit();
}
$stmt2 = $conn->prepare("
    SELECT ar.id, ar.absent_date, ar.reason, ar.attachment, ar.status,
           COALESCE(s.s_id,'') AS s_id,
           COALESCE(CONCAT(s.s_fname,' ',s.s_lname),'') AS student_name,
           COALESCE(ss.subject_code,'') AS subject_code,
           COALESCE(ss.day_of_week,'') AS day_of_week,
           COALESCE(ss.start_time,'') AS start_time,
           COALESCE(ss.end_time,'') AS end_time,
           COALESCE(sec.section_code,'') AS section_code
    FROM absent_requests ar
    LEFT JOIN students s ON ar.s_id = s.s_id
    LEFT JOIN sections_schedules ss ON ar.ss_id = ss.ss_id
    LEFT JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ar.id = ?
");
$stmt2->bind_param("i", $request_id);
$stmt2->execute();
$updated_row = $stmt2->get_result()->fetch_assoc();

$rowData = [
    htmlspecialchars($updated_row['student_name']), // Child
    htmlspecialchars($updated_row['section_code']), // Section
    htmlspecialchars($updated_row['subject_code']), // Subject
    $updated_row['day_of_week'].' '.substr($updated_row['start_time'],0,5).'-'.substr($updated_row['end_time'],0,5), // Schedule
    $updated_row['absent_date'], // Date of Absence
    htmlspecialchars($updated_row['reason']), // Reason
    // Attachment column: button with icon, data-attachment stores filename
    '<button class="btn btn-sm view-edit-request fw-bold" 
        style="background: var(--primary); color: var(--tertiary);"
        data-id="'.$updated_row['id'].'"
        data-child="'.htmlspecialchars($updated_row['student_name']).'"
        data-section="'.htmlspecialchars($updated_row['section_code']).'"
        data-subject="'.htmlspecialchars($updated_row['subject_code']).'"
        data-schedule="'.$updated_row['day_of_week'].' '.substr($updated_row['start_time'],0,5).'-'.substr($updated_row['end_time'],0,5).'"
        data-absent_date="'.$updated_row['absent_date'].'"
        data-reason="'.htmlspecialchars($updated_row['reason']).'"
        data-attachment="'.(!empty($updated_row['attachment']) ? basename($updated_row['attachment']) : '').'">
        <i class="bi bi-pencil-square"></i>
    </button>',
    // Status column
    '<span class="badge '.
        ($updated_row['status'] === 'Pending' ? 'bg-warning text-dark' :
        ($updated_row['status'] === 'Approved' ? 'bg-success' : 'bg-danger')).
    '">'.$updated_row['status'].'</span>'
];




echo json_encode([
    'status'=>'success',
    'message'=>'Request updated successfully',
    'updated_row_id'=>$request_id,
    'updated_row_data'=>$rowData
]);

