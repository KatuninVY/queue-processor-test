<?php
spl_autoload_register();

$app = new classes\App(
	1000,
	10000,
	10,
	10000,
	0
);

$start_time = microtime(true);

$app->run();

echo "\nProcessing time: ".(microtime(true) - $start_time)."\n";
