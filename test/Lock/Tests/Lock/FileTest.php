<?php

namespace Lock\Tests\Lock;

use DateTime;
use Exception;
use Lock\File;
use PHPUnit_Framework_TestCase;

class FileTest extends PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider provideTestConstructData
     */
    public function testConstruct($filename, $format = null)
    {

        $lock = new File($filename, $format);
        $this->assertEquals($filename, $lock->getFilename());

        if(!is_null($filename)) {
            $lock = new File($filename, File::MODE_DELETE, $format);
            $this->assertEquals($filename, $lock->getFilename());
            $this->assertEquals($format, $lock->getDateFormat());
        }
    }

    public function provideTestConstructData()
    {
        $fixturesDir = __DIR__ . '/../fixtures';
        return array(
            array(
                $fixturesDir . 'new-file.LOCK',
            ),
            array(
                $fixturesDir . 'new-2-file.LOCK',
                'Y-m-d h:i:s',
            ),
        );
    }

    public function testCompareAge()
    {
        $file = __DIR__ . '/../fixtures/existing-file.LOCK';
        $lock = new File($file);
        $this->assertEquals(-1, $lock->compareAge(new DateTime('Monday, 15-Aug-05 15:52:00 UTC')));
        $this->assertEquals(0, $lock->compareAge(new DateTime('Monday, 15-Aug-05 15:52:01 UTC')));
        $this->assertEquals(1, $lock->compareAge(new DateTime('Monday, 15-Aug-05 15:52:02 UTC')));
    }

    /**
     * @dataProvider provideTestAcquireData
     */
    public function testAcquire($filename, $exception = false)
    {
        if($exception) {
            $this->setExpectedException('Lock\RuntimeException');
        }
        $lock = new File($filename);
        $lock->acquire(false);

        $this->assertTrue(file_exists($lock->getFilename()));

        $lock2 = new File($filename);
        $this->assertFalse($lock2->acquire(false));

        $lock->release();
        $this->assertFalse(file_exists($lock->getFilename()));

        $lock3 = new File($filename);
        $lock3->acquire(false);
        $this->assertTrue(file_exists($lock->getFilename()));
        $lock3->release();
        $this->assertFalse(file_exists($lock->getFilename()));

    }

    public function provideTestAcquireData()
    {
        $fixturesDir = __DIR__ . '/../fixtures/';
        return array(
            array(
                $fixturesDir . 'new-file.LOCK',
                false
            ),
            array(
                $fixturesDir . 'existing-file.LOCK',
                true
            ),
        );
    }


    /**
     * @dataProvider provideTestAcquireWaitData
     */
    public function testAcquireWait($filename)
    {
        $lock = new File($filename, File::MODE_TRUNCATE);
        $lock->acquire();

        $pid = pcntl_fork();
        if ($pid == -1) {
             throw new Exception('could not fork');
        }

        if ($pid) {
            // parent thread
            $start = time();
            $lock2 = new File($filename, File::MODE_TRUNCATE);
            $lock2->acquire(true);
            $end = time();
            $this->assertEquals(1, $end - $start);
            $lock2->release();
            unlink($lock2->getFilename());
        } else {
            sleep(1);
            $lock->release();
            exit(0);
        }

    }

    public function provideTestAcquireWaitData()
    {
        $fixturesDir = __DIR__ . '/../fixtures/';
        return array(
            array(
                $fixturesDir . 'new-file.LOCK',
            ),
        );
    }
}
