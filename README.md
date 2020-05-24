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
$pm = ProcessManager::get('example');

if ($pm->lock()) {
    // Do your job
 
    $pm->release();   
} else {
    // All threads are engaged
}
```

## Closure style

Two instances of cron task may run simultaneously.

_converter.php_

```php
$lock = ProcessManager::get('converter')
    ->threads(2)
    ->lock(function(ProcessManager $pm) {
        // Do your job
    
        // Autorelease
    });

if ( ! $lock) {
    // All threads are engaged
}
```

## Subject

With queue, we need to control each task will execute just once.

We may define process subject with task number (or something like that).

In the example there will be only one instance with exact subject.
But you may run second thread of `converter` with different subject.

```php
ProcessManager::get('converter')
    ->threads(2)
    ->subject('video.mp4')
    ->lock(function(ProcessManager $pm) {
        // Do your job
    });
```

## How it works

Process Manager keep locks in temp files.

Lock file stores process id. Project Manager watches if process is alive
and automatically release lock if process disappears. 

Even if your task running for hours — manager will watch it activity.

```php
ProcessManager::get('converter')
    // Define custom dir to store lock files
    // System tmp dir used by default
    ->dir('/var/lock');
```