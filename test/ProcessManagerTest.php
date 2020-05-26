<?php


use Cellard\ProcessManager\ProcessManager;
use PHPUnit\Framework\TestCase;

class ProcessManagerTest extends TestCase
{

    protected $pm;

    protected function setUp(): void
    {
        parent::setUp();

        ProcessManager::$driver = new \Cellard\ProcessManager\Drivers\FilesystemDriver();
        $this->pm = ProcessManager::queue('unit-test');
    }

    public function testLock()
    {
        // Allowed
        $this->assertTrue($this->pm->lock());
        // Still allowed as pid was not changed
        $this->assertTrue($this->pm->lock());

        // Denied, as we have one thread limit
        $this->pm->pid(getmypid() * rand(10, 20));
        $this->assertFalse($this->pm->lock());

        // Allowed, as we increase limit
        $this->pm->threads(2);
        $this->assertTrue($this->pm->lock());
        $this->pm->release();

        $this->pm->pid(getmypid());
        $this->pm->release();

        // After all releases there should not be any locks
        $this->assertEquals(0, count($this->pm->threads()));
    }

    public function testProcessExists()
    {
        // Real process should exist
        $this->assertTrue($this->pm->processExists(getmypid()));

        // Fake process shouldn't
        $this->assertFalse($this->pm->processExists(getmypid() * rand(10, 20)));
    }

    public function testThreads()
    {
        // Check response structure
        $this->assertIsArray($this->pm->threads());
        $this->assertEquals(0, count($this->pm->threads()));

        // One lock
        $this->pm->lock();
        // No matter how any times one process ask for lock
        $this->pm->lock();
        $this->assertEquals(1, count($this->pm->threads()));
        $this->assertTrue(in_array(getmypid(), $this->pm->threads()));

        $this->pm->release();
        $this->assertEquals(0, count($this->pm->threads()));

        // Few locks
        $this->pm->threads(2);
        $this->pm->lock();
        $this->pm->pid(getmypid() * rand(10, 20));
        $this->pm->lock();
        // Still should be just 1 active process (as fake process will be filtered out)
        $this->assertEquals(1, count($this->pm->threads()));

        $this->pm->pid(getmypid());
        $this->pm->release();

        // After all releases there should not be any locked threads
        $this->assertEquals(0, count($this->pm->threads()));
    }

    public function testLockWithSubjects()
    {
        $subject = 'thread-subject';

        $this->pm->subject($subject);

        // Allowed
        $this->assertTrue($this->pm->lock());
        // Still allowed as pid was not changed
        $this->assertTrue($this->pm->lock());

        // Denied, as subject is locked
        $this->pm->pid(getmypid() * rand(10, 20));
        $this->assertFalse($this->pm->lock());

        $this->pm->pid(getmypid());
        $this->pm->release();

        // Allowed, as subject was released
        $this->pm->pid(getmypid() * rand(10, 20));
        $this->assertTrue($this->pm->lock());
        $this->pm->release();

        // After all releases there should not be any locks
        $this->assertEquals(0, count($this->pm->threads()));

    }

    public function testSubject()
    {
        $subject1 = 'thread-subject-1';
        $subject2 = 'thread-subject-2';
        $fake = getmypid() * rand(10, 20);

        // We need few threads to test subject switching
        $this->pm->threads(2);

        $this->pm->subject($subject1);

        // Allowed
        $this->assertTrue($this->pm->lock());

        // Then changing subject, it releases previous subject
        $this->pm->subject($subject2);

        $this->pm->subject($subject1);
        $this->pm->pid($fake);

        // Allowed, as subject1 was released
        $this->assertTrue($this->pm->lock());

        $this->pm->pid(getmypid());
        // Still allowed, as fake pid was ignored
        $this->assertTrue($this->pm->lock());

        // As we change pid, so changing to subject2 will not release subject1
        $this->pm->pid($fake);
        $this->pm->subject($subject2);
        $this->pm->pid(getmypid());
        // Now both subjects locked for real pid
        $this->assertTrue($this->pm->lock());

        // Denied
        $this->pm->pid($fake);
        $this->assertFalse($this->pm->lock());

        // Release all subjects and thread
        $this->pm->pid(getmypid());
        $this->pm->subject($subject1);
        $this->pm->release();
        $this->pm->subject($subject2);
        $this->pm->release();

        $this->assertEquals(0, count($this->pm->threads()));
    }
}
