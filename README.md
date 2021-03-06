# [Cron] Process Manager

Manager allows you to control cron task threads.

```
#crontab
* * * * * php example.php
* * * * * php converter.php
```

Only one instance of cron task is allowed. Next one will be rejected if previous still running.

_example.php_

```php
$pm = new ProcessManager('example');

if ($pm->lock()) {
    // Do your job
 
} else {
    // All threads are engaged
}
```

As process management based on process id you do not need to manually release lock, it will be auto released.

Instead of instantiating `ProcessManager` class you may use static shortcut.

```php
$pm = ProcessManager::queue('example');
```

## Closure style

In this example two instances of cron task may run simultaneously.

_converter.php_

```php
$lock = ProcessManager::queue('converter')
    ->threads(2)
    ->lock(function(ProcessManager $pm) {
        // Do your job
    
    });

if ( ! $lock) {
    // All threads are engaged
}
```

## Subject

When working with queue, we need to control each task will be executed just once.

We may define process subject with task number (or something like that).

In this example there will be maximum two processes, and each file will be converted just once.

```php
$pm = ProcessManager::queue('converter')
    ->threads(2);

if (!$pm->lock()) {
    exit('Too many threads');
}

while (FilesToConvert::getOne() as $filename) {
    if (!$pm->subject($filename)->lock()) {
        // file is converting now by other thread
        continue;
    }
    
    // Your code to convert file here
}
```

This example may be shortened.

```php
while (FilesToConvert::getOne() as $filename) {
    ProcessManager::queue('converter')
        ->threads(2)
        ->subject($filename)
        ->lock(ProcessManager $pm) use ($filename) {
            // Your code to convert file here
        });
}
```

## How it works

Process Manager keeps locks with process id.
It watches if process is alive and automatically releases lock if process disappears. 

Even if your task running for hours — manager will watch it activity. 

### Drivers

#### Filesystem

Used by default. Manager keeps lock files in system temp directory, but you may redefine it.

```php
ProcessManager::$driver = new \Cellard\ProcessManager\Drivers\FilesystemDriver(/* optional */ $loc_dir);
```

#### Redis

```php
ProcessManager::$driver = new \Cellard\ProcessManager\Drivers\RedisDriver(/* optional */ $predis_config);
```

### Namespaces

As manager store locks in Filesystem or in Redis keys, it is possible that different projects will use the same names.
To prevent conflicts it is recommended to redefine manager's prefix.

```php
ProcessManager::$prefix = 'your-project-name';
```
