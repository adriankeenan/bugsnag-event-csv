<?php

require __DIR__.'/../vendor/autoload.php';

use Keenan\Command;
use Symfony\Component\Console\Application;

$command = new Command();

$application = new Application();
$application->add($command);
$application->setDefaultCommand($command->getName(), true);
$application->run();