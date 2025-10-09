<?php
require_once __DIR__ . '/../../includes/db.php';

if (isset($_GET['s_id'])) {
    $s_id = intval($_GET['s_id']);

    $query = $conn->prepare("
        SELECT subject_code, section_code, time_in, time_out, status 
        FROM attendance 
        WHERE s_id = ?
        ORDER BY time_in DESC
    ");
    $query->bind_param("i", $s_id);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows > 0) {
        $counter = 1;
        while ($row = $result->fetch_assoc()) {
                echo '<tr>
                <td>' . $counter++ . '</td>
                <td>' . htmlspecialchars($row['subject_code']) . '</td>
                <td>' . htmlspecialchars($row['section_code']) . '</td>
                <td>' . ($row['time_in'] ? date("M d, Y h:i A", strtotime($row['time_in'])) : '-') . '</td>
                <td>' . ($row['time_out'] ? date("M d, Y h:i A", strtotime($row['time_out'])) : '-') . '</td>
                <td><span class="badge bg-' .
                    ($row['status'] == "Present" ? "success" : ($row['status'] == "Late" ? "warning" : "danger")) .
                    '">' . htmlspecialchars($row['status']) . '</span></td>
            </tr>';

        }
    } else {
        // NO TABLE ROWS at all
        echo '<tr><td colspan="6" class="text-center">No attendance records found for this student.</td></tr>';
    }
} else {
    echo '<tr><td colspan="6" class="text-center">Invalid request.</td></tr>';
}
