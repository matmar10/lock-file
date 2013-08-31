matmar10/lock-file
==================

OOP lock file implementation using php flock

Usage
-----

```PHP

use Lock\File;

$fileName = __DIR__.'/some-process.LOCK';

$lock = new File($fileName);
$lock->acquire();

// do some stuff

$lock->release();



