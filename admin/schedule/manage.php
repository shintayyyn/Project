<?php
require_once __DIR__ . '/../../includes/db.php';

// Update timestamp after schedule changes
$update_query = "UPDATE sections_schedules 
                SET updated_at = CURRENT_TIMESTAMP 
                WHERE section_id = ?";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("i", $section_id);
$stmt->execute();