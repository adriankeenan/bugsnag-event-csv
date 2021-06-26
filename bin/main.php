<?php

require __DIR__.'/../vendor/autoload.php';

use Keenan\Command;
use Symfony\Component\Console\Application;

$command = new Command();

$application = new Application('bugsnag-event-csv', '1.1');
$application->add($command);
$application->setDefaultCommand($command->getName(), true);
$application->run();