<?php
require __DIR__ . '/../vendor/autoload.php';

$pm = \Cellard\ProcessManager\ProcessManager::get('test');
$pm->dir(__DIR__ . '/loc');

return $pm;