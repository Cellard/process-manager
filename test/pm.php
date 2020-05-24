<?php
require __DIR__ . '/../vendor/autoload.php';

return \Cellard\ProcessManager\ProcessManager::get('test')
    ->dir(__DIR__ . '/loc');
