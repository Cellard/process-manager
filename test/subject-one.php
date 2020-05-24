<?php
require __DIR__ . '/../vendor/autoload.php';

$pm = \Cellard\ProcessManager\ProcessManager::queue('test/subject.php')
    ->dir(__DIR__ . '/loc');

$lock = $pm
    ->subject('one+/.subject')
    ->threads(2)
    ->lock(function (\Cellard\ProcessManager\ProcessManager $pm) {
        $threads = count($pm->activeThreads());
        echo "Engage thread number {$threads}\n";
        echo "Script will die in 60 seconds\n";
        sleep(60);
    });

if (!$lock) {
    echo "Denied\n";
}
