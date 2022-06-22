<?php


use Cellard\ProcessManager\Drivers\FilesystemDriver;

class FilesystemDriverTest extends DriverTest
{
    protected function setUp()
    {
        parent::setUp();

        $this->driver = new FilesystemDriver(__DIR__ . '/locks');
    }

}
