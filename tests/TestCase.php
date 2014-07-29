<?php

namespace League\FactoryMuffin\Test;

use League\FactoryMuffin\Facade\FactoryMuffin;

class TestCase extends \PHPUnit_Framework_TestCase
{
    public static function setupBeforeClass()
    {
        FactoryMuffin::loadFactories(__DIR__ . '/factories');
        FactoryMuffin::setSaveMethod('save');
    }

    public static function tearDownAfterClass()
    {
        FactoryMuffin::setDeleteMethod('delete');
        FactoryMuffin::deleteSaved();
    }
}