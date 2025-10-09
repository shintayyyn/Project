<?php
error_reporting(1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status'  => 'error',
        'title'   => 'Invalid Request',
        'message' => 'Only POST requests are allowed.'
    ]);
    exit();
}

// Collect POST data
$parent_id = $_POST['p_id'] ?? null;
$fname     = trim($_POST['p_fname'] ?? '');
$lname     = trim($_POST['p_lname'] ?? '');
$mname     = trim($_POST['p_mname'] ?? '');
$suffix    = trim($_POST['p_suffix'] ?? '');
$gender    = trim($_POST['p_gender'] ?? '');
$bdate     = trim($_POST['p_bdate'] ?? '');
$cnum      = trim($_POST['p_cnum'] ?? '');
$email     = trim($_POST['p_email'] ?? '');
$status    = $_POST['p_status'] ?? 'active';
$address   = trim($_POST['p_address'] ?? '');
$child_id  = $_POST['child_id'] ?? null;

// Validate required fields
if (!$parent_id || !$fname || !$lname || !$gender || !$bdate || !$cnum || !$email) {
    echo json_encode([
        'status'  => 'error',
        'title'   => 'Missing Fields',
        'message' => 'Please fill out all required fields before saving.'
    ]);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'status'  => 'error',
        'title'   => 'Invalid Email',
        'message' => 'Please enter a valid email address.'
    ]);
    exit();
}

// Check duplicate email
$check_stmt = $conn->prepare("SELECT p_id FROM parents WHERE p_email = ? AND p_id != ?");
$check_stmt->bind_param("si", $email, $parent_id);
$check_stmt->execute();
$check_stmt->store_result();
if ($check_stmt->num_rows > 0) {
    echo json_encode([
        'status'  => 'error',
        'title'   => 'Duplicate Email',
        'message' => 'This email address is already registered to another parent.'
    ]);
    exit();
}
$check_stmt->close();

// --- Handle Avatar Upload ---
// --- Handle Avatar Upload ---
$avatar_path = "/Project/uploads/parents/parent_{$parent_id}.jpg"; 
$server_path = $_SERVER['DOCUMENT_ROOT'] . $avatar_path;

if (isset($_FILES['p_avatar']) && $_FILES['p_avatar']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['p_avatar']['tmp_name'];
    $fileType    = mime_content_type($fileTmpPath);

    // Allow only image types
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode([
            'status'  => 'error',
            'title'   => 'Invalid File',
            'message' => 'Only JPG and PNG images are allowed for profile picture.'
        ]);
        exit();
    }

    // Move file (overwrite if exists)
    if (!move_uploaded_file($fileTmpPath, $server_path)) {
        echo json_encode([
            'status'  => 'error',
            'title'   => 'Upload Failed',
            'message' => 'Error while uploading the profile picture.'
        ]);
        exit();
    }

    // ✅ Save blob into DB as well
    $avatar_blob = file_get_contents($server_path);

    $blob_stmt = $conn->prepare("UPDATE parents SET profile_path = ?, profile_blob = ? WHERE p_id = ?");
    $null = NULL; // placeholder if blob empty
    $blob_stmt->bind_param("sbi", $avatar_path, $avatar_blob, $parent_id);

    // Must send blob via send_long_data for large files
    $blob_stmt->send_long_data(1, $avatar_blob);

    $blob_stmt->execute();
    $blob_stmt->close();
}

// Update parent info
$stmt = $conn->prepare("
    UPDATE parents 
    SET p_fname = ?, p_lname = ?, p_mname = ?, p_suffix = ?, 
        p_gender = ?, p_bdate = ?, p_cnum = ?, p_email = ?, 
        p_status = ?, p_address = ?
    WHERE p_id = ?
");
$stmt->bind_param(
    "ssssssssssi",
    $fname, $lname, $mname, $suffix,
    $gender, $bdate, $cnum, $email,
    $status, $address, $parent_id
);

if (!$stmt->execute()) {
    echo json_encode([
        'status'  => 'error',
        'title'   => 'Update Failed',
        'message' => 'Something went wrong while updating the parent information.'
    ]);
    exit();
}

// --- Update parent_student table if child selected ---
if ($child_id) {
    // Remove existing links
    $conn->query("DELETE FROM parent_student WHERE p_id = $parent_id");

    // Insert new link
    $insert_link = $conn->prepare("INSERT INTO parent_student (p_id, s_id) VALUES (?, ?)");
    $insert_link->bind_param("ii", $parent_id, $child_id);
    $insert_link->execute();
}

// Fetch updated parent info along with child name and section
$parent_stmt = $conn->prepare("
    SELECT p.*, s.s_fname, s.s_lname, ss.section_code
    FROM parents p
    LEFT JOIN parent_student ps ON p.p_id = ps.p_id
    LEFT JOIN students s ON ps.s_id = s.s_id
    LEFT JOIN students_sections ss ON s.s_id = ss.s_id
    WHERE p.p_id = ?
    LIMIT 1
");
$parent_stmt->bind_param("i", $parent_id);
$parent_stmt->execute();
$result = $parent_stmt->get_result();
$row = $result->fetch_assoc();

// Generate full name and initials
$full_name = htmlspecialchars(
    $row['p_lname'] . 
    (!empty($row['p_suffix']) ? ' ' . $row['p_suffix'] : '') .
    ', ' . $row['p_fname'] .
    (!empty($row['p_mname']) ? ' ' . strtoupper(substr($row['p_mname'], 0, 1)) . '.' : '')
);

$initials = strtoupper(substr($row['p_fname'],0,1) . substr($row['p_lname'],0,1));

// Child info
$child_fullname = $row['s_fname'] ? "{$row['s_fname']} {$row['s_lname']}" : null;
$child_section  = $row['section_code'] ?? null;

// Check avatar exists

$avatar_exists = file_exists($server_path);

// ✅ Fetch blob directly from DB
$blob_stmt = $conn->prepare("SELECT profile_blob FROM parents WHERE p_id = ?");
$blob_stmt->bind_param("i", $parent_id);
$blob_stmt->execute();
$blob_result = $blob_stmt->get_result();
$blob_row = $blob_result->fetch_assoc();
$blob_stmt->close();

$avatar_blob = $blob_row && $blob_row['profile_blob'] 
    ? 'data:image/jpeg;base64,' . base64_encode($blob_row['profile_blob']) 
    : '';


echo json_encode([
    'status'  => 'success',
    'title'   => 'Updated',
    'message' => 'Parent information has been updated successfully.',
    'parent'  => [
        'p_id'         => $row['p_id'],
        'full_name'    => $full_name,
        'initials'     => $initials,
        'p_email'      => $row['p_email'],
        'p_status'     => $row['p_status'],
        'p_gender'     => $row['p_gender'],
        'p_bdate'      => $row['p_bdate'],
        'p_cnum'       => $row['p_cnum'],
        'p_address'    => $row['p_address'],
        'avatar'       => $avatar_exists ? $avatar_path . "?t=" . time() : '',
        'avatar_blob'  => $avatar_blob, 
        'child_name'   => $child_fullname,
        'section_code' => $child_section
    ]
]);
