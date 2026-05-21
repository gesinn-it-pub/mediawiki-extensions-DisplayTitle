<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'includes',
		'../../extensions/Scribunto',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/Scribunto',
		'vendor/',
	]
);

$cfg['baseline_path'] = __DIR__ . '/baseline.php';

return $cfg;
