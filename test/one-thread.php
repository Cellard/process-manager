<?php
$pm = require 'pm.php';

$lock = $pm->lock(function($pm) {
    echo "Script will die in 60 seconds\n";
    sleep(60);
});

if (!$lock) {
    echo "Denied\n";
}
