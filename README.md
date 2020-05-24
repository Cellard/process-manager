# [Cron] Process Manager

Only one instance of cron task is allowed. Next one will be rejected if previous still running.

```php
$pm = ProcessManager::get('converter');

if ($pm->lock()) {
    // Do your job
 
    $pm->release();   
}
```

Closure style.
Two instances of cron task may run simultaneously.

```php
ProcessManager::get('converter')
    ->threads(2)
    ->lock(function(ProcessManager $pm) {
        // Do your job
    
        // Autorelease
    });
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
    ->dir('/var/lock');
```