<?php
// Router for PHP built-in server
// This ensures all requests are routed through index.php

$requested_file = __DIR__ . $_SERVER['REQUEST_URI'];

// If requesting a real file (CSS, JS, images), serve it
if (is_file($requested_file)) {
    return false;
}

// Everything else goes through index.php
require_once __DIR__ . '/index.php';
?>
