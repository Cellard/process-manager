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
        //echo "Run external process " . file_get_contents($pidfile) . "\n";

        $pid = trim(file_get_contents($outputfile));

        return $pid;
    }
    
    protected function setUp(): void
    {
        parent::setUp();

        $this->pm = ProcessManager::queue('unit-test');
    }

    protected function waitForRelease()
    {
        while ($this->pm->threads()) {
            sleep(1);
        }
        echo "All locks released\n";
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
     * Test external thread executor works
     */
    public function testExec()
    {
        echo "---\n";
        echo "Test running external thread\n";

        $pid = $this->execExternal();
        $this->assertExternal($pid);
        $this->assertThreads(1);

        echo "External thread exists\n";
        $this->waitForRelease();
    }

    /**
     * Test single thread lock without subject
     */
    public function testLock()
    {
        echo "---\n";
        echo "Test locking too many threads\n";

        $this->assertExternal($this->execExternal());
        $this->assertThreads(1);

        $this->assertExternalFailed($this->execExternal());
        $this->assertThreads(1);

        echo "Second thread could not get lock\n";

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
     * Assert number of locked threads
     * @param $count
     */
    protected function assertThreads($count)
    {
        $this->assertIsArray($this->pm->threads());
        $this->assertEquals($count, count($this->pm->threads()));
    }

    /**
     * Test few threads locking without subject
     */
    public function testLockThreads()
    {
        echo "---\n";
        echo "Test working with few treads\n";

        // Check response structure
        $this->assertThreads(0);

        // Few locks
        echo "Locking first thread\n";
        $pid = $this->execExternal(2);
        $this->assertExternal($pid);
        $this->assertThreads(1);

        echo "Locking second thread\n";
        $pid = $this->execExternal(2);
        $this->assertExternal($pid);
        $this->assertThreads(2);

        echo "Third thread could not get lock\n";
        $pid = $this->execExternal(2);
        $this->assertExternalFailed($pid);
        $this->assertThreads(2);

        $this->waitForRelease();
    }

    public function testLockWithSubjects()
    {
        echo "---\n";
        echo "Test working with subjects\n";

        echo "Locking single thread with subject\n";
        $pid = $this->execExternal(1, 'subject-1');
        $this->assertExternal($pid);

        echo "Second thread with such subject could not get lock\n";
        $pid = $this->execExternal(1, 'subject-1');
        $this->assertExternalFailed($pid);

        echo "Second thread with different subject could not get lock\n";
        $pid = $this->execExternal(1, 'subject-2');
        $this->assertExternalFailed($pid);

        $this->waitForRelease();

        echo "Locking first thread with subject\n";
        $pid = $this->execExternal(2, 'subject-1');
        $this->assertExternal($pid);
        $this->assertThreads(1);

        echo "Subject can not be locked\n";
        $pid = $this->execExternal(2, 'subject-1');
        $this->assertExternalFailed($pid);

        echo "Locking second thread with different subject\n";
        $pid = $this->execExternal(2, 'subject-2');
        $this->assertExternal($pid);
        $this->assertThreads(2);

        echo "No more threads could get lock\n";
        $pid = $this->execExternal(2, 'subject-3');
        $this->assertExternalFailed($pid);

        $this->waitForRelease();
    }
}
