<?php
// @phan-file-suppress PhanUndeclaredClassAliasOriginal
// MW 1.39 uses Scribunto_LuaLibraryBase; the new namespace is MW 1.41+

namespace MediaWiki\Extension\DisplayTitle;

// Scribunto_LuaLibraryBase was moved to
// MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase in the Scribunto
// version bundled with MediaWiki 1.41+. Create a namespace-local alias that
// resolves to whichever class is actually available so that DisplayTitleLuaLibrary
// can extend it without needing a runtime version check.
if ( class_exists( 'MediaWiki\\Extension\\Scribunto\\Engines\\LuaCommon\\LibraryBase' ) ) {
	class_alias(
		'MediaWiki\\Extension\\Scribunto\\Engines\\LuaCommon\\LibraryBase',
		'MediaWiki\\Extension\\DisplayTitle\\LuaLibraryBase'
	);
} elseif ( class_exists( 'Scribunto_LuaLibraryBase' ) ) {
	class_alias(
		'Scribunto_LuaLibraryBase',
		'MediaWiki\\Extension\\DisplayTitle\\LuaLibraryBase'
	);
}
