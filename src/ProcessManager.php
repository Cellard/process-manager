<?php


namespace Cellard\ProcessManager;

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
    protected $domain;
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

    public function __construct($domain)
    {
        $this->domain = $domain;
        $this->maxThreads = 1;
        $this->subject = null;
        $this->dir = sys_get_temp_dir();
        $this->pid = getmypid();

        if (!$this->processExists($this->pid)) {
            throw new ProcessManagerException("Can't resolve process list");
        }
    }

    /**
     * Get instance
     * @param string $domain
     * @return static
     * @throws ProcessManagerException
     */
    public static function get($domain)
    {
        return new static($domain);
    }

    /**
     * Set number of threads
     * @param integer $threads
     * @return static
     */
    public function threads($threads)
    {
        $this->maxThreads = $threads;
        return $this;
    }

    /**
     * Define subject
     * @param $subject
     * @return static
     */
    public function subject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Define directory to keep lock files
     * @param string $dir
     * @return static
     */
    public function dir($dir)
    {
        $this->dir = $dir;
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        return $this;
    }

    /**
     * Set process ID (testing purposes)
     * @param integer $pid
     * @return static
     * @deprecated
     * @internal
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
        if ($this->lockThread()) {
            if ($this->lockProcess()) {

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

    /**
     * Get listing of active threads
     * @return integer[]|array
     */
    public function activeThreads()
    {
        $tmp = $this->threadLockFile();

        if (!file_exists($tmp) || !($pids = file($tmp))) {
            return [];
        }

        // Keep only active
        return array_filter($pids, function ($pid) {
            return $this->processExists($pid);
        });
    }

    public function threadLockFile()
    {
        return $this->dir . "/pm-{$this->domain}.loc";
    }

    public function processLockFile()
    {
        return $this->dir . "/pm-{$this->domain}-{$this->subject}.loc";
    }

    /**
     * Tries to lock thread
     * @return bool
     */
    protected function lockThread()
    {
        $pids = $this->activeThreads();

        if (in_array($this->pid, $pids)) {
            // one process may not engage few threads
            return false;
        }

        $threadsRunning = count($pids);

        if ($threadsRunning < $this->maxThreads) {
            $pids[] = $this->pid;
            return (boolean)file_put_contents($this->threadLockFile(), implode("\n", $pids));
        }

        return false;
    }

    protected function releaseThread()
    {
        $tmp = $this->threadLockFile();

        $pids = $this->activeThreads();

        // Remove our pid from list
        $pids = array_filter($pids, function ($pid) {
            return $pid && $pid != $this->pid;
        });

        if ($pids) {
            file_put_contents($tmp, implode("\n", $pids));
        } elseif (file_exists($tmp)) {
            unlink($tmp);
        }
    }

    /**
     * Check whether process exist
     * @param integer $pid
     * @return boolean
     */
    public function processExists($pid)
    {
        // TODO Now it supports only *nix systems
        return (integer)$pid && (boolean)exec("ps ahxwwo pid | grep {$pid}");
    }

    /**
     * Tries to lock process
     * @return bool
     */
    protected function lockProcess()
    {
        if ($this->subject) {

            $tmp = $this->processLockFile();

            if (!file_exists($tmp)) {
                return (boolean)file_put_contents($tmp, $this->pid);
            }

            $pid = file_get_contents($tmp);

            if (!$this->processExists($pid)) {
                // snatch lock!
                return (boolean)file_put_contents($tmp, $this->pid);
            }

            return false;
        } else {
            // Neutral answer
            return true;
        }
    }

    protected function releaseProcess()
    {
        if ($this->subject) {
            $tmp = $this->processLockFile();

            if (file_exists($tmp)) {
                unlink($tmp);
            }
        }
    }

    /**
     * Release process
     */
    public function release()
    {
        $this->releaseProcess();
        $this->releaseThread();
    }
}