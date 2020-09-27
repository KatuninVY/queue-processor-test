<?php

spl_autoload_register();

$app = new classes\App(
	10,
	100,
	5,
	5
);

$app->run();