<?php

// Ensure writable temporary folders exist for serverless execution
$folders = [
    '/tmp/storage/app/public',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
    '/tmp/views',
];

foreach ($folders as $folder) {
    if (!is_dir($folder)) {
        @mkdir($folder, 0755, true);
    }
}

// Forward Vercel serverless requests to public/index.php
require __DIR__ . '/../public/index.php';
