<?php

$finder = PhpCsFixer\Finder::create()
	->exclude('vendor')
	->in(__DIR__ . '/../');

$config = new PhpCsFixer\Config();

return $config->setFinder($finder)
	->setRiskyAllowed(true)
	->setRules([
		'native_function_invocation' => [
			'include' => ['@compiler_optimized'],
		],
	])
	->setIndent("\t");
