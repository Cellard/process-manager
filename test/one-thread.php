<?php
require __DIR__ . '/../vendor/autoload.php';

$pm = \Cellard\ProcessManager\ProcessManager::queue('one-thread.php')
    ->dir(__DIR__ . '/loc');

$lock = $pm->lock(function ($pm) {
    echo "Script will die in 60 seconds\n";
    sleep(60);
});

if (!$lock) {
    echo "Denied\n";
}
