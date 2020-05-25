<?php
/** @var \Cellard\ProcessManager\ProcessManager $pm */
$pm = require 'pm.php';

$targets = ['one', 'two', 'three', 'four', 'five'];

$pm->threads(2);
if (!$pm->lock()) {
    echo "Locked thread " . implode(', ', $pm->activeThreads()) . "\n";
    exit();
} else {
    $threads = count($pm->activeThreads());
    echo "Engage thread " . implode(', ', $pm->activeThreads()) . "\n";
}

foreach ($targets as $target) {
    if ($pm->subject($target)->lock()) {
        echo "Engage target {$target}\n";
        sleep(600);
        echo "Release target {$target}\n";
    } else {
        echo "Locked target {$target}\n";
    }
}

$pm->release();
echo "Release thread " . implode(', ', $pm->activeThreads()) . "\n";
