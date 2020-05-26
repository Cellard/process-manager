<?php

@list($script, $sleep, $queue, $threads, $subject) = $argv;

if (!$sleep || !$queue) {
    die("usage: {$script} sleep-in-seconds queue-name max-threads(1) subject-name(optional)\n");
}
if (!$threads) {
    $threads = 1;
}

require __DIR__ . '/../vendor/autoload.php';

$pm = \Cellard\ProcessManager\ProcessManager::queue($queue)
    ->threads($threads);

if ($subject) {
    $pm->subject($subject);
}

if ($pm->lock()) {
    echo getmypid() . "\n";
    sleep($sleep);
    $pm->release();
} else {
    echo 0;
}


