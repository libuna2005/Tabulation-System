<?php
define('NO_SESSION', 1);
require __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(array_merge(board(), [
    'updated'   => date('g:i:s A'),
    'locked'    => (int)setting('locked'),
    'concluded' => (int)setting('concluded'),
]));
