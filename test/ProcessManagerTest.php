<?php


use Cellard\ProcessManager\ProcessManager;
use PHPUnit\Framework\TestCase;

class ProcessManagerTest extends TestCase
{

    protected $pm;

    /**
     * Run thread
     * @param $sleep
     * @param $queue
     * @param int $threads
     * @param string $subject
     * @return integer process id
     */
    protected function execExternal($threads = 1, $subject = '', $sleep = 4, $queue = 'unit-test')
    {
        $cmd = 'php ' . __DIR__ . "/thread.php {$sleep} {$queue} {$threads} {$subject}";
        $outputfile = __DIR__ . "/output.txt";
        $pidfile = __DIR__ . "/pid.txt";

        exec(sprintf("%s > %s 2>&1 & echo $! > %s", $cmd, $outputfile, $pidfile));

        sleep (1); // Wait for $outputfile

        return trim(file_get_contents($outputfile));
    }
    
    protected function setUp()
    {
        parent::setUp();

        $this->pm = ProcessManager::queue('unit-test');
    }

    protected function waitForRelease()
    {
        while ($this->pm->threads()) {
            sleep(1);
        }
    }

    /**
     * Assert pid get lock and running
     * @param $pid
     */
    protected function assertExternal($pid)
    {
        $this->assertGreaterThan(0, $pid, "External thread failed to lock");
        $this->assertTrue($this->pm->processExists($pid), "Process {$pid} not exists");
        $this->assertTrue(in_array($pid, $this->pm->threads()), "Process {$pid} is not in thread list");
    }

    protected function assertExternalFailed($pid)
    {
        $this->assertEquals(0, $pid);
    }

    /**
     * Assert number of locked threads
     * @param $count
     */
    protected function assertThreads($count)
    {
        $this->assertTrue(is_array($this->pm->threads()));
        $this->assertEquals($count, count($this->pm->threads()));
    }

    /**
     * Test external thread executor works
     */
    public function testExec()
    {
        $pid = $this->execExternal();
        $this->assertExternal($pid);

        $this->waitForRelease();
    }

    public function testProcessExists()
    {
        // Real process should exist
        $this->assertTrue($this->pm->processExists(getmypid()));

        // Fake process shouldn't
        $this->assertFalse($this->pm->processExists(getmypid() * rand(10, 20)));
    }

    /**
     * Test single thread lock without subject
     */
    public function testLockOneThread()
    {
        $this->assertThreads(0);

        $this->assertExternal($this->execExternal());
        $this->assertThreads(1);

        $this->assertExternalFailed($this->execExternal());
        $this->assertThreads(1);

        $this->waitForRelease();
    }

    /**
     * Test few threads locking without subject
     */
    public function testLockFewThreads()
    {
        $this->assertThreads(0);

        $pid = $this->execExternal(2);
        $this->assertExternal($pid);
        $this->assertThreads(1);

        $pid = $this->execExternal(2);
        $this->assertExternal($pid);
        $this->assertThreads(2);

        $pid = $this->execExternal(2);
        $this->assertExternalFailed($pid);
        $this->assertThreads(2);

        $this->waitForRelease();
    }

    public function testLockOneThreadWithSubject()
    {
        $this->assertThreads(0);

        $pid = $this->execExternal(1, 'subject', 5);
        $this->assertExternal($pid);
        $this->assertThreads(1);

        $pid = $this->execExternal(1, 'subject', 5);
        $this->assertExternalFailed($pid);
        $this->assertThreads(1);

        $pid = $this->execExternal(1, 'next-subject', 5);
        $this->assertExternalFailed($pid);
        $this->assertThreads(1);

        $this->waitForRelease();
    }

    public function testLockFewThreadsWithSubject()
    {
        $this->assertThreads(0);

        $pid = $this->execExternal(2, 'subject-one', 7);
        $this->assertExternal($pid);
        $this->assertThreads(1);

        $pid = $this->execExternal(2, 'subject-one', 7);
        $this->assertExternalFailed($pid);
        $this->assertThreads(1);

        $pid = $this->execExternal(2, 'subject-two', 7);
        $this->assertExternal($pid);
        $this->assertThreads(2);

        $pid = $this->execExternal(2, 'subject-two', 7);
        $this->assertExternalFailed($pid);
        $this->assertThreads(2);

        $pid = $this->execExternal(2, 'subject-tree', 7);
        $this->assertExternalFailed($pid);
        $this->assertThreads(2);

        $this->waitForRelease();
    }
}
