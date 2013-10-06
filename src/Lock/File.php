<?php

namespace Lock;

use DateTime;
use Exception;
use Lock\RuntimeException;

class File
{

    const MODE_DELETE = 'DELETE';

    const MODE_TRUNCATE = 'TRUNCATE';

    protected $filename;

    protected $mode;

    protected $dateFormat;

    protected $fileHandle;

    public function __construct($filename, $mode = self::MODE_DELETE, $dateFormat = DateTime::RFC850)
    {
        $this->filename = $filename;
        $this->mode = $mode;
        $this->dateFormat = $dateFormat;
    }

    protected function initHandle()
    {
        $mode = (file_exists($this->filename)) ? 'r+' : 'x+';
        $this->fileHandle = fopen($this->filename, $mode);
        if(!$this->fileHandle) {
            throw new RuntimeException(sprintf("Could not read lock file '%s'", $this->filename));
        }
    }

    public function acquire($wait = true)
    {
        // in delete mode, file indicates lock is already acquired
        if(!$wait && self::MODE_DELETE === $this->mode && file_exists($this->filename)) {
            return false;
        }

        $this->initHandle();

        // LOCK_BN is a bitmask to change behavior of flock
        // see: http://php.net/manual/en/function.flock.php
        $operation = ($wait) ? LOCK_EX : LOCK_EX | LOCK_NB;
        if(!flock($this->fileHandle, $operation)) {
                return false;
        }

        ftruncate($this->fileHandle, 0);
        $time = new DateTime();
        fwrite($this->fileHandle, $time->format($this->dateFormat));
        fflush($this->fileHandle);

        return true;
    }

    public function release()
    {
        if(!file_exists($this->filename)) {
            throw new RuntimeException(sprintf("Could not release lock for file '%s': file does not exists", $this->filename));
        }

        try {
            flock($this->fileHandle, LOCK_UN);
        } catch(Exception $e) {
            throw new RuntimeException(sprintf("Could not release lock for file '%s': " . $e->getMessage(), $this->filename));
        }
        if(self::MODE_DELETE === $this->mode) {
            if(!unlink($this->filename)) {
                throw new RuntimeException(sprintf("Could not release lock for file '%s': PHP unlink (delete) of lock file failed", $this->filename));
            }
        } else {
            ftruncate($this->fileHandle, 0);
        }
        fclose($this->fileHandle);
    }

    public function compareAge(DateTime $compareToDateTime)
    {
        if(!$this->fileHandle) {
            $this->initHandle();
        }

        $compareToTimestamp = $compareToDateTime->getTimestamp();
        rewind($this->fileHandle);
        $lockFileDateTime = new DateTime(fread($this->fileHandle, filesize($this->filename)+1));
        $lockFileTimestamp = $lockFileDateTime->getTimestamp();

        // less thanve
        if($compareToTimestamp < $lockFileTimestamp) {
            return -1;
        }

        // equal
        if($compareToTimestamp === $lockFileTimestamp) {
            return 0;
        }

        // greater than
        // $compareToTimestamp > $lockFileTimestamp
        return 1;
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function setDateFormat($dateFormat)
    {
        $this->dateFormat = $dateFormat;
    }

    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    public function getMode()
    {
        return $this->mode;
    }
}
