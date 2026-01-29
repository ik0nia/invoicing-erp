<?php

$publicIndex = __DIR__ . '/public/index.php';

if (file_exists($publicIndex)) {
    require $publicIndex;
    return;
}

http_response_code(500);
header('Content-Type: text/plain; charset=utf-8');
echo "Lipseste public/index.php. Seteaza document root la /public sau adauga structura Laravel completa.";
