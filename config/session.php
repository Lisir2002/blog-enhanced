<?php

return [
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'cookie' => 'blog_session',
];
