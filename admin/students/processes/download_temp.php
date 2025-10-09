<?php
if (!isset($_GET['file'])) exit('No file specified');

$file = sys_get_temp_dir() . '/' . basename($_GET['file']);

if (!file_exists($file)) exit('File not found');

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file));

readfile($file);
unlink($file); // optional: delete after download
exit;
