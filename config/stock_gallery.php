<?php

return [
    'python' => env('STOCK_GALLERY_PYTHON', storage_path('app/stock-gallery-runtime/' . (PHP_OS_FAMILY === 'Windows' ? 'Scripts/python.exe' : 'bin/python'))),
    'queue_connection' => env('STOCK_GALLERY_QUEUE_CONNECTION', 'database'),
    'queue' => 'stock-gallery',
];
