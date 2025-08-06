<?php

// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// ini_set('log_errors', 1);
// ini_set('error_log', 'error.log');

$aiPath = dirname(__DIR__) . '/modules/ai/ai.php';


// echo $aiPath;

if ($aiPath && file_exists($aiPath)) {
    include $aiPath;
} else {
    exit;
}

// Optional: Notify about job
notify("2", "New Jobs Have Been Added", 200, 3);

// Run grabEssay 5 times
for ($i = 1; $i <= 5; $i++) {
    grabEssay();
}

// Final response
sendJsonResponse(200);
