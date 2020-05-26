<?php


namespace Cellard\ProcessManager\Drivers;


trait Sanitizer
{
    /**
     * Sanitize string to use it as filename or redis key etc
     * @param string $string
     * @return string
     */
    protected function sanitize($string)
    {
        return preg_replace('/[^a-z0-9]+/', '-', strtolower($string));
    }
}