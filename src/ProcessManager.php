<?php


namespace Cellard\ProcessManager;


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
        $this->queue = $this->sanitizeFilename($queue);
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
     * @param string $queue
     * @return static
     * @throws ProcessManagerException
     */
    public static function queue($queue)
    {
        return new static($queue);
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
        // Release previous subject
        $this->releaseSubject();

        $this->subject = $this->sanitizeFilename($subject);
        return $this;
    }

    /**
     * Redefine directory to keep lock files
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

    protected function sanitizeFilename($string)
    {
        // sanitize filename
        $string = preg_replace(
            '~
        [<>:"/\\|?*]|            # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
        [\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
        [\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
        [#\[\]@!$&\'()+,;=]|     # URI reserved https://tools.ietf.org/html/rfc3986#section-2.2
        [{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
        ~x',
            '-', $string);
        // avoids ".", ".." or ".hiddenFiles"
        $string = ltrim($string, '.-');
        // optional beautification
        //if ($beautify) $filename = beautify_filename($string);
        // maximize filename length to 255 bytes http://serverfault.com/a/9548/44086
        $ext = pathinfo($string, PATHINFO_EXTENSION);
        $string = mb_strcut(pathinfo($string, PATHINFO_FILENAME), 0, 100 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($string)) . ($ext ? '.' . $ext : '');
        return $string;
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

        // trim crlf
        $pids = array_map(function($pid) {
            return (integer)$pid;
        }, $pids);

        // Keep only active
        $pids = array_filter($pids, function ($pid) {
            return $pid && $this->processExists($pid);
        });

        return $pids;
    }

    protected function threadLockFile()
    {
        return $this->dir . "/pm-{$this->queue}.loc";
    }

    protected function subjectLockFile()
    {
        return $this->dir . "/pm-{$this->queue}-{$this->subject}.loc";
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
        $pids = $this->activeThreads();

        if (in_array($this->pid, $pids)) {
            // already engaged (in case of changing subject)
            return true;
        }

        $threadsRunning = count($pids);

        if ($threadsRunning < $this->maxThreads) {
            $pids[] = $this->pid;
            return (boolean)file_put_contents($this->threadLockFile(), implode("\n", array_filter($pids)));
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

            $tmp = $this->subjectLockFile();

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

    protected function releaseSubject()
    {
        if ($this->subject) {
            $tmp = $this->subjectLockFile();

            if (file_exists($tmp) && ($pid = file_get_contents($tmp)) && ($pid == $this->pid)) {
                unlink($tmp);
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
        $mask = $this->subjectLockFileMask();
        foreach (glob($mask) as $filename) {
            $pid = file_get_contents($filename);
            if (!$this->processExists($pid)) {
                unlink($filename);
            }
        }
    }
}