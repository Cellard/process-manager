<?php


use Cellard\ProcessManager\Drivers\RedisDriver;

class RedisDriverTest extends DriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new RedisDriver();
    }
}