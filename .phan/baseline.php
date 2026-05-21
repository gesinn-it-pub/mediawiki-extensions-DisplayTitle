<?php
/**
 * This is an automatically generated baseline for Phan issues.
 * When Phan is invoked with --load-baseline=path/to/baseline.php,
 * The pre-existing issues listed in this file won't be emitted.
 *
 * This file can be updated by invoking Phan with
 * --save-baseline=path/to/baseline.php (can be combined with --load-baseline)
 */
return [
	// # Issue statistics:
	// PhanUndeclaredMethod : 6 occurrences
	// PhanTypeMismatchArgumentNullable : 1 occurrence
	// PhanUndeclaredExtendedClass : 1 occurrence

	// Currently, file_suppressions and directory_suppressions are the only supported suppressions
	'file_suppressions' => [
		// $html nullable type in handleLink() – existing design; MW 1.36+ method_exists guards
		'includes/DisplayTitleHooks.php' => [
			'PhanTypeMismatchArgumentNullable',
			'PhanUndeclaredMethod',
		],
		// Scribunto_LuaLibraryBase and its methods are not available in the analysis environment
		'includes/DisplayTitleLuaLibrary.php' => [
			'PhanUndeclaredExtendedClass',
			'PhanUndeclaredMethod',
		],
	],
];
