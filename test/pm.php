<?php
require __DIR__ . '/../vendor/autoload.php';

return \Cellard\ProcessManager\ProcessManager::queue('test')
    ->dir(__DIR__ . '/loc');
