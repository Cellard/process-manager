<?php
/** @var \Cellard\ProcessManager\ProcessManager $pm */
$pm = require 'pm.php';

$lock = $pm
    ->subject('one')
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
