<?php

include 'vendor/autoload.php';

use EdinburghCouncil\CSV2GeoJson;

if ($argc < 2) {
	die('Usage: php ' . $argv[0] . ' filename.csv' . PHP_EOL);
}

// Set global bcmath precision
bcscale(ini_get('precision'));

// Pull in any command line arguments
parse_str(implode('&', array_slice($argv, 2)), $options);

try {
	$converter = new CSV2GeoJson($argv[1], $options);
	echo $converter->write(), PHP_EOL;
} catch (Exception $e) {
	die($e->getMessage() . PHP_EOL);
}
