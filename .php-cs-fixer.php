<?php

declare(strict_types=1);

use PhpCsFixer\Finder;
use Yard\PhpCsFixerRules\Config;

$finder = Finder::create()
	->in(__DIR__)
	->append(['.php-cs-fixer.php'])
	->name('*.php')
	->ignoreDotFiles(true)
	->ignoreVCS(true)
	->exclude('vendor')
	->exclude('wp-content');

return Config::create($finder)->mergeRules(['binary_operator_spaces' => [
	'default' => 'single_space',
	'operators' => [
		'=>' => null,
		'|' => 'single_space',
	]],
]);
