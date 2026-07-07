<?php
if (!defined('BASE_URL')) {
    $scriptDir = str_replace('\\', '/', __DIR__);
    $docRoot   = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $relPath   = rtrim(str_replace($docRoot, '', $scriptDir), '/');
    $baseUrl   = dirname($relPath);
    define('BASE_URL', $baseUrl === '.' || $baseUrl === '/' ? '' : $baseUrl);
}
