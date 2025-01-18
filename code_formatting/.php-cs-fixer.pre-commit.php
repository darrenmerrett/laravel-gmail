<?php

ini_set('memory_limit', '512000000');

/** @var \PhpCsFixer\Config $config */
$config = require __DIR__ . '/.php-cs-fixer.dist.php';

return $config
	->setRules(
		array_merge(
			$config->getRules(),
			['@PSR12' => true],
		),
	);
