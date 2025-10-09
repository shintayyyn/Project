<?php
/**
 * getAvatarHTML()
 * Returns an <img> tag (file or base64) or an initials div.
 *
 * @param mysqli $conn
 * @param int|null $userId
 * @param string $type   'parent'|'student'|'teacher'
 * @param int $size      px size (both width and height)
 * @return string        HTML safe to echo
 */
function getAvatarHTML($conn, $userId, $type = 'parent', $size = 35) {
    // validate type
    $map = [
        'parent'  => ['table' => 'parents',  'id' => 'p_id', 'fname' => 'p_fname', 'lname' => 'p_lname'],
        'student' => ['table' => 'students', 'id' => 's_id', 'fname' => 's_fname', 'lname' => 's_lname'],
        'teacher' => ['table' => 'teachers', 'id' => 't_id', 'fname' => 't_fname', 'lname' => 't_lname'],
    ];
    if (!isset($map[$type])) {
        return '<div class="profile-avatar" style="width:'.$size.'px;height:'.$size.'px;font-size:'.intval($size/2).'px;">?</div>';
    }

    // if no id, return placeholder initials "??"
    if (empty($userId)) {
        return '<div class="profile-avatar d-flex align-items-center justify-content-center"'
            .' style="width:'.$size.'px;height:'.$size.'px;font-size:'.intval($size/2).'px;">'
            .'??</div>';
    }

    $info = $map[$type];

    // 1) Try filesystem paths (common variants)
    $candidates = [
        "/uploads/{$type}s/{$type}_{$userId}.jpg",
        "/uploads/{$type}s/{$type}_{$userId}.jpeg",
        "/uploads/{$type}s/{$type}_{$userId}.png",
        "uploads/{$type}s/{$type}_{$userId}.jpg",
        "uploads/{$type}s/{$type}_{$userId}.jpeg",
        "uploads/{$type}s/{$type}_{$userId}.png",
        "/Project/uploads/{$type}s/{$type}_{$userId}.jpg",
        "/Project/uploads/{$type}s/{$type}_{$userId}.png",
    ];

    foreach ($candidates as $rel) {
        $server = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . $rel;
        if (file_exists($server)) {
            // use the relative URL (strip leading server dir if present)
            $url = $rel;
            // ensure leading slash for browser path
            if ($url[0] !== '/') $url = '/' . ltrim($url, '/');
            return '<div class="profile-avatar d-flex align-items-center justify-content-center"'
                .' style="width:'.$size.'px;height:'.$size.'px;border-radius:50%;overflow:hidden;">'
                .'<img src="'.htmlspecialchars($url, ENT_QUOTES).'?t='.time().'" alt="Avatar"'
                .' style="width:100%;height:100%;object-fit:cover;">'
                .'</div>';
        }
    }

    // 2) Try database blob column named 'profile_blob' and also fetch names for initials
    $table   = $info['table'];
    $idcol   = $info['id'];
    $fname   = $info['fname'];
    $lname   = $info['lname'];

    $sql = "SELECT profile_blob, $fname AS fname, $lname AS lname, profile_path 
            FROM {$table} WHERE {$idcol} = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row) {
            // if profile_path exists in DB and file exists, prefer it
            if (!empty($row['profile_path'])) {
                $path = $row['profile_path'];
                // make sure path starts with slash for browser
                if ($path[0] !== '/') $path = '/' . ltrim($path, '/');
                $serverPath = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . $path;
                if (file_exists($serverPath)) {
                    return '<div class="profile-avatar d-flex align-items-center justify-content-center"'
                        .' style="width:'.$size.'px;height:'.$size.'px;border-radius:50%;overflow:hidden;">'
                        .'<img src="'.htmlspecialchars($path, ENT_QUOTES).'?t='.time().'" alt="Avatar"'
                        .' style="width:100%;height:100%;object-fit:cover;">'
                        .'</div>';
                }
            }

            if (!empty($row['profile_blob'])) {
                $base64 = base64_encode($row['profile_blob']);
                // try to guess MIME type (default png)
                // assume jpeg for typical photo storage; if you know the mime store it separately
                $mime = 'image/jpeg';
                return '<div class="profile-avatar d-flex align-items-center justify-content-center"'
                    .' style="width:'.$size.'px;height:'.$size.'px;border-radius:50%;overflow:hidden;">'
                    .'<img src="data:'.$mime.';base64,'. $base64 .'" alt="Avatar"'
                    .' style="width:100%;height:100%;object-fit:cover;">'
                    .'</div>';
            }

            // fallback to initials using fetched names if any
            $f = isset($row['fname']) ? trim($row['fname']) : '';
            $l = isset($row['lname']) ? trim($row['lname']) : '';
            $initials = '';
            if ($f !== '') $initials .= mb_strtoupper(mb_substr($f, 0, 1));
            if ($l !== '') $initials .= mb_strtoupper(mb_substr($l, 0, 1));
            if ($initials === '') $initials = '??';

            return '<div class="profile-avatar d-flex align-items-center justify-content-center"'
                .' style="width:'.$size.'px;height:'.$size.'px;font-size:'.intval($size/2).'px;">'
                .htmlspecialchars($initials, ENT_QUOTES)
                .'</div>';
        }
    }

    // 3) final fallback — unknown user or missing DB row
    return '<div class="profile-avatar d-flex align-items-center justify-content-center"'
        .' style="width:'.$size.'px;height:'.$size.'px;font-size:'.intval($size/2).'px;">'
        .'??</div>';
}
