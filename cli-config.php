<?php

use Doctrine\ORM\Tools\Console\ConsoleRunner;

list($em, $_) = require_once __DIR__.'/examples/bootstrap.php';

return ConsoleRunner::createHelperSet($em);
