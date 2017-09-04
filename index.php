<?php
// Bulk load libraries which are installed by composer
require_once __DIR__ . '/vendor/autoload.php';

// Get a value from POST, then show
$inputString = file_get_contents('php://input');
error_log($inputString);
?>
