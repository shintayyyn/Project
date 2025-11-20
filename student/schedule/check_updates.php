<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$student_id = $_SESSION['user_id'];
$last_check = isset($_GET['last_check']) ? floatval($_GET['last_check']) : 0;

// 🧠 Get the active academic term
$term_query = "
    SELECT at.term_id
    FROM academic_terms at
    INNER JOIN academic_years ay ON ay.ay_id = at.ay_id
    WHERE at.is_active = 1
    ORDER BY ay.year_start DESC,
             FIELD(at.semester, 'First', 'Second', 'Summer') DESC
    LIMIT 1
";
$term_result = $conn->query($term_query);
$term_data = $term_result->fetch_assoc();
$active_term_id = $term_data['term_id'] ?? null;

if (!$active_term_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No active academic term found']);
    exit;
}

// 🧩 Determine if the student is regular or irregular
$is_regular_query = "SELECT is_regular FROM students WHERE s_id = ?";
$stmt = $conn->prepare($is_regular_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$is_regular_result = $stmt->get_result();
$is_regular = $is_regular_result->fetch_assoc()['is_regular'] ?? 1;
$stmt->close();

// 🕒 Check updates for both Regular & Irregular students
$query = "
    SELECT UNIX_TIMESTAMP(MAX(latest_update)) AS last_update, 
           MAX(latest_update) AS formatted_time
    FROM (
        -- 🔹 Regular student schedule updates
        SELECT ss.updated_at AS latest_update
        FROM sections_schedules ss
        INNER JOIN students_sections stsec ON ss.section_id = stsec.section_id
        WHERE stsec.s_id = ? 
          AND ss.term_id = ?

        UNION ALL

        -- 🔹 Irregular student schedule updates
        SELECT ss.updated_at AS latest_update
        FROM subject_enrollments se
        INNER JOIN sections_schedules ss 
            ON se.subject_code = ss.subject_code 
           AND ss.term_id = se.term_id
        WHERE se.s_id = ?
          AND se.term_id = ?
    ) AS combined_updates
";
$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $student_id, $active_term_id, $student_id, $active_term_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$current_update = floatval($row['last_update']);
if ($current_update <= 0) {
    $current_update = time();
}

// 🪵 Log debug info
error_log("Check Updates - Last Check: $last_check, Current Update: $current_update, Raw Time: " . $row['formatted_time']);

header('Content-Type: application/json');
echo json_encode([
    'hasUpdates' => $current_update > $last_check,
    'timestamp' => $current_update,
    'debug' => [
        'last_check' => $last_check,
        'current_update' => $current_update,
        'formatted_time' => $row['formatted_time'],
        'term_id' => $active_term_id,
        'is_regular' => $is_regular
    ]
]);
?>
