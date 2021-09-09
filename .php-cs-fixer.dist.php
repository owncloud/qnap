<?php

$config = new OC\CodingStandard\Config();

$config
	->setUsingCache(true)
	->getFinder()
	->exclude('l10n')
	->exclude('vendor')
	->exclude('vendor-bin')
	->in(__DIR__);

return $config;
