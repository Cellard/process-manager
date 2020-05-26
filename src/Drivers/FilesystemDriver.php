<?php

namespace Cellard\ProcessManager\Drivers;

use Cellard\ProcessManager\Drivers\Sanitizer;
use Cellard\ProcessManager\ProcessManager;

class FilesystemDriver implements ProcessManagerDriverInterface
{
    use Sanitizer;

    protected $dir;

    /**
     * FilesystemDriver constructor.
     * @param null|string $dir path to store files (system temp by default)
     */
    public function __construct($dir = null)
    {
        $this->dir = $dir ? $dir : sys_get_temp_dir();
        if (!file_exists($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
    }

    protected function filename($thread, $subject = null)
    {
        $prefix = $this->sanitize(ProcessManager::$prefix ? ProcessManager::$prefix : 'proc-man');

        $path = $this->dir . '/';
        $path .= $prefix . '-' . $this->sanitize($thread);
        if ($subject) {
            if ($subject === '*') {
                $path .= '-*';
            } else {
                $path .= '-' . $this->sanitize($subject);
            }
        }
        $path .= '.loc';

        return $path;
    }

    /**
     * Get identifiers of processes that works on task with given name
     * @param string $name
     * @return integer[]|array
     */
    public function getThreads($name)
    {
        $tmp = $this->filename($name);

        if (!file_exists($tmp) || !($identifiers = file($tmp))) {
            return [];
        }

        // trim crlf
        return array_map(function ($pid) {
            return (integer)$pid;
        }, $identifiers);
    }

    /**
     * Update list of processes that works on task with given name
     * @param string $name
     * @param array|integer[] $identifiers
     * @return boolean
     */
    public function setThreads($name, $identifiers)
    {
        $tmp = $this->filename($name);
        if ($identifiers) {
            return (boolean)file_put_contents($tmp, implode("\n", array_filter($identifiers)));
        } elseif (file_exists($tmp)) {
            return unlink($tmp);
        } else {
            return true;
        }
    }

    /**
     * Get identifier of process that works on task with given name and on subject
     * @param string $name
     * @param string $subject
     * @return integer|null
     */
    public function getThreadWithSubject($name, $subject)
    {
        $tmp = $this->filename($name, $subject);

        if (!file_exists($tmp)) {
            return null;
        }

        return (integer)file_get_contents($tmp) ? (integer)file_get_contents($tmp) : null;
    }

    /**
     * Update list of processes that works on task with given name and on subject
     * @param string $name
     * @param string $subject
     * @param integer $identifier
     * @return boolean
     */
    public function setThreadWithSubject($name, $subject, $identifier)
    {
        return (boolean)file_put_contents($this->filename($name, $subject), $identifier);
    }

    /**
     * Remove identifier from task with given name and from subject
     * @param string $name
     * @param string $subject
     * @return boolean
     */
    public function unsetThreadWithSubject($name, $subject)
    {
        return unlink($this->filename($name, $subject));
    }

    /**
     * Get list of registered subjects
     * @param string $name
     * @return array|string[]
     */
    public function getSubjects($name)
    {
        $mask = $this->filename($name, '*');
        $affix = explode('*', $mask);
        $subjects = [];

        foreach (glob($mask) as $filename) {
            $subjects[] = str_replace($affix[0], '', str_replace($affix[1], '', $filename));
        }

        return $subjects;
    }
}