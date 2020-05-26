<?php


namespace Cellard\ProcessManager\Drivers;


use Cellard\ProcessManager\Drivers\Sanitizer;
use Cellard\ProcessManager\ProcessManager;
use Predis\Client;

class RedisDriver implements ProcessManagerDriverInterface
{
    use Sanitizer;

    /**
     * @var Client
     */
    protected $client;

    /**
     * RedisDriver constructor.
     * @param null|string|array $predis_config passed directly to Predis\Client
     */
    public function __construct($predis_config = null)
    {
        $this->client = new Client($predis_config);
    }

    protected function key($name, $subject = null)
    {
        $prefix = $this->sanitize(ProcessManager::$prefix ? ProcessManager::$prefix : 'proc-man');

        return $prefix . ':' . $this->sanitize($name) .
            ($subject ? ':' . ($subject === '*' ? '*' : $this->sanitize($subject)) : '');
    }

    /**
     * Get identifiers of processes that works on task with given name
     * @param string $name
     * @return integer[]|array
     */
    public function getThreads($name)
    {
        $identifiers = explode(',', $this->client->get($this->key($name)));

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
        return (boolean)$this->client->set($this->key($name), implode(',', $identifiers));
    }

    /**
     * Get list of registered subjects
     * @param string $name
     * @return array|string[]
     */
    public function getSubjects($name)
    {
        $mask = $this->key($name, '*');
        $affix = explode('*', $mask);
        $subjects = [];

        foreach ($this->client->keys($mask) as $key) {
            $subjects[] = str_replace($affix[0], '', str_replace($affix[1], '', $key));
        }

        return $subjects;
    }

    /**
     * Get identifier of process that works on task with given name and on subject
     * @param string $name
     * @param string $subject
     * @return integer|null
     */
    public function getThreadWithSubject($name, $subject)
    {
        $value = (integer)$this->client->get($this->key($name, $subject));
        return $value ? $value : null;
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
        return (boolean)$this->client->set($this->key($name, $subject), $identifier);
    }

    /**
     * Remove identifier from task with given name and from subject
     * @param string $name
     * @param string $subject
     * @return boolean
     */
    public function unsetThreadWithSubject($name, $subject)
    {
        return (boolean)$this->client->del([$this->key($name, $subject)]);
    }
}