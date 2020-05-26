<?php

namespace Cellard\ProcessManager\Drivers;
interface ProcessManagerDriverInterface
{
    /**
     * Get identifiers of processes that works on task with given name
     * @param string $name
     * @return integer[]|array
     */
    public function getThreads($name);

    /**
     * Update list of processes that works on task with given name
     * @param string $name
     * @param array|integer[] $identifiers
     * @return boolean
     */
    public function setThreads($name, $identifiers);

    /**
     * Get list of registered subjects
     * @param string $name
     * @return array|string[]
     */
    public function getSubjects($name);

    /**
     * Get identifier of process that works on task with given name and on subject
     * @param string $name
     * @param string $subject
     * @return integer|null
     */
    public function getThreadWithSubject($name, $subject);

    /**
     * Update list of processes that works on task with given name and on subject
     * @param string $name
     * @param string $subject
     * @param integer $identifier
     * @return boolean
     */
    public function setThreadWithSubject($name, $subject, $identifier);

    /**
     * Remove identifier from task with given name and from subject
     * @param string $name
     * @param string $subject
     * @return boolean
     */
    public function unsetThreadWithSubject($name, $subject);
}