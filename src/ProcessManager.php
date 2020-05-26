<?php


namespace Cellard\ProcessManager;


use Cellard\ProcessManager\Drivers\FilesystemDriver;
use Cellard\ProcessManager\Drivers\ProcessManagerDriverInterface;
use mozartk\ProcessFinder\ProcessFinder;

/**
 * Manages processes. Control threads and process state using process id
 * @package Cellard\ProcessManager
 */
class ProcessManager
{
    /**
     * Process class, e.g. `converter`
     * @var string
     */
    protected $queue;
    /**
     * Process subject, e.g. `video.mp4`
     * @var string
     */
    protected $subject;
    /**
     * @var int
     */
    protected $maxThreads;

    protected $dir;

    protected $pid;

    public function __construct($queue)
    {
        $this->queue = $queue;
        $this->maxThreads = 1;
        $this->subject = null;
        $this->dir = sys_get_temp_dir();
        $this->pid = getmypid();

        if (!$this->processExists($this->pid)) {
            throw new ProcessManagerException("Can't resolve process list");
        }
    }

    public static $prefix = 'proc-man';
    /**
     * Uses FilesystemDriver by default
     * @var ProcessManagerDriverInterface
     */
    public static $driver;

    /**
     * @return ProcessManagerDriverInterface
     */
    protected function driver()
    {
        if (!self::$driver) {
            self::$driver = new FilesystemDriver();
        }
        return self::$driver;
    }

    /**
     * Get instance
     * @param string $queue
     * @return static
     * @throws ProcessManagerException
     */
    public static function queue($queue)
    {
        return new static($queue);
    }

    /**
     * Set (or get) number (or list) of maximum (or running) threads
     * @param integer|null $threads
     * @return static|integer[]|array
     */
    public function threads($threads = null)
    {
        if (is_null($threads)) {

            $pids = $this->driver()->getThreads($this->queue);

            // Keep only active
            $pids = array_filter($pids, function ($pid) {
                return $pid && $this->processExists($pid);
            });

            return $pids;

        } else {
            $this->maxThreads = $threads;
            return $this;
        }
    }

    /**
     * Set (or get) subject
     * @param string|null $subject
     * @return static|string
     */
    public function subject($subject = null)
    {
        if ($subject) {
            // Release previous subject
            $this->releaseSubject();

            $this->subject = $subject;
            return $this;
        } else {
            return $this->subject;
        }
    }

    /**
     * Redefine process id (test purposes)
     *
     * @internal
     * @deprecated
     * @param $pid
     * @return $this
     */
    public function pid($pid)
    {
        $this->pid = $pid;
        return $this;
    }

    /**
     * Try to lock process
     * @param callable $body function(ProcessManager $pm) with autorelease
     * @return bool
     */
    public function lock(callable $body = null)
    {
        $this->cleanup();

        if ($this->lockThread()) {
            if ($this->lockSubject()) {

                if ($body) {
                    // Closure style
                    $body($this);

                    $this->release();
                }

                return true;
            }
            $this->releaseThread();
        }
        return false;
    }

    protected function subjectLockFileMask()
    {
        return $this->dir . "/pm-{$this->queue}-*.loc";
    }

    /**
     * Tries to lock thread
     * @return bool
     */
    protected function lockThread()
    {
        $threads = $this->threads();

        if (in_array($this->pid, $threads)) {
            // already engaged (in case of changing subject)
            return true;
        }

        if (count($threads) < $this->maxThreads) {
            // Add our pid to the list
            $threads[] = $this->pid;
            return $this->driver()->setThreads($this->queue, $threads);
        }

        return false;
    }

    protected function releaseThread()
    {
        $threads = $this->threads();

        // Remove our pid from the list
        $threads = array_filter($threads, function ($pid) {
            return $pid && $pid != $this->pid;
        });

        $this->driver()->setThreads($this->queue, $threads);
    }

    /**
     * Check whether process exist
     * @param integer $pid
     * @return boolean
     */
    public function processExists($pid)
    {
        $processHandler = new ProcessFinder();
        return $processHandler->isRunning($pid);
    }

    /**
     * Tries to lock process
     * @return bool
     */
    protected function lockSubject()
    {
        if ($this->subject) {

            $pid = $this->driver()->getThreadWithSubject($this->queue, $this->subject);

            if (!$pid || !$this->processExists($pid)) {
                // snatch lock!
                return $this->driver()->setThreadWithSubject($this->queue, $this->subject, $this->pid);
            } elseif ($pid == $this->pid) {
                // already engaged
                return true;
            }

            return false;
        } else {
            // Neutral answer
            return true;
        }
    }

    protected function releaseSubject()
    {
        if ($this->subject) {
            if ($this->pid == $this->driver()->getThreadWithSubject($this->queue, $this->subject)) {
                $this->driver()->unsetThreadWithSubject($this->queue, $this->subject);
            }
        }
    }

    /**
     * Release lock
     */
    public function release()
    {
        $this->releaseSubject();
        $this->releaseThread();
    }

    protected function cleanup()
    {
        // Find and remove all subject lock files with dead processes

        foreach ($this->driver()->getSubjects($this->queue) as $subject) {
            $pid = $this->driver()->getThreadWithSubject($this->queue, $subject);
            if (!$this->processExists($pid)) {
                $this->driver()->unsetThreadWithSubject($this->queue, $subject);
            }
        }
    }
}