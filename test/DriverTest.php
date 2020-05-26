<?php

use Cellard\ProcessManager\Drivers\ProcessManagerDriverInterface;
use PHPUnit\Framework\TestCase;

abstract class DriverTest extends TestCase
{
    /**
     * @var ProcessManagerDriverInterface
     */
    protected $driver;

    public function testThreadWithSubject()
    {
        $pid = rand(1111, 9999);

        $this->driver->setThreadWithSubject('name', 'subject', $pid);
        $this->assertEquals($pid, $this->driver->getThreadWithSubject('name', 'subject'));

        $this->driver->unsetThreadWithSubject('name', 'subject');
        $this->assertNull($this->driver->getThreadWithSubject('name', 'subject'));
    }

    public function testThreads()
    {
        $pid1 = rand(1111, 9999);
        $pid2 = rand(1111, 9999);

        $this->driver->setThreads('name', []);

        $this->driver->setThreads('name', [$pid1, $pid2]);
        $this->assertIsArray($this->driver->getThreads('name'));
        $this->assertEquals(2, count($this->driver->getThreads('name')));
        $this->assertTrue(in_array($pid1, $this->driver->getThreads('name')));
        $this->assertTrue(in_array($pid2, $this->driver->getThreads('name')));

        $pid3 = rand(1111, 9999);
        $this->driver->setThreads('name', [$pid1, $pid2, $pid3]);
        $this->assertEquals(3, count($this->driver->getThreads('name')));
        $this->assertTrue(in_array($pid1, $this->driver->getThreads('name')));
        $this->assertTrue(in_array($pid2, $this->driver->getThreads('name')));
        $this->assertTrue(in_array($pid3, $this->driver->getThreads('name')));

        $this->driver->setThreads('name', []);
    }

    public function testSubjects()
    {
        $pid1 = rand(1111, 9999);
        $pid2 = rand(1111, 9999);

        $this->driver->setThreadWithSubject('name', 'subject-1', $pid1);
        $this->driver->setThreadWithSubject('name', 'subject-2', $pid2);

        $this->assertIsArray($this->driver->getSubjects('name'));
        $this->assertTrue(in_array('subject-1', $this->driver->getSubjects('name')));
        $this->assertTrue(in_array('subject-2', $this->driver->getSubjects('name')));

        $this->driver->unsetThreadWithSubject('name', 'subject-1');
        $this->driver->unsetThreadWithSubject('name', 'subject-2');
    }
}
