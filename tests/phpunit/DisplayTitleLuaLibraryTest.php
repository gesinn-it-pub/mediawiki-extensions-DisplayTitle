<?php

namespace MediaWiki\Extension\DisplayTitle\Tests;

use MediaWiki\Context\RequestContext;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ParserOptions;
use WikitextContent;

/**
 * @covers \MediaWiki\Extension\DisplayTitle\DisplayTitleLuaLibrary
 * @group Database
 */
class DisplayTitleLuaLibraryTest extends MediaWikiIntegrationTestCase {

	private const MODULE = <<<'LUA'
local p = {}
function p.get( frame )
	return mw.ext.displaytitle.get( frame.args[1] )
end
function p.set( frame )
	mw.ext.displaytitle.set( frame.args[1] )
	return 'done'
end
return p
LUA;

	public function setUp(): void {
		parent::setUp();
		if ( !class_exists( 'MediaWiki\\Extension\\Scribunto\\Engines\\LuaCommon\\LibraryBase' ) ) {
			$this->markTestSkipped( 'Scribunto is not installed' );
		}
		$this->overrideConfigValues( [
			'AllowDisplayTitle' => true,
			'RestrictDisplayTitle' => false
		] );
		RequestContext::getMain()->setTitle( Title::newFromText( 'Main Page' ) );
		$this->editPage( 'Module:DisplayTitleLuaTest', self::MODULE );
		$this->editPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$this->editPage( 'Elephant Page', 'Content' );
	}

	private function parse( string $wikitext ): ParserOutput {
		$title = Title::newFromText( 'Test Page' );
		$user = $this->getTestSysop()->getUser();
		return $this->getServiceContainer()->getContentRenderer()->getParserOutput(
			new WikitextContent( $wikitext ), $title->toPageIdentity(), null, new ParserOptions( $user )
		);
	}

	public function testGetReturnsDisplayTitle() {
		$output = $this->parse( '{{#invoke:DisplayTitleLuaTest|get|Dingo Page}}' );

		$this->assertStringContainsString( 'Zebra', $output->getRawText() );
	}

	public function testGetReturnsPageNameWithoutDisplayTitle() {
		$output = $this->parse( '{{#invoke:DisplayTitleLuaTest|get|Elephant Page}}' );

		$this->assertStringContainsString( 'Elephant Page', $output->getRawText() );
	}

	public function testGetReturnsPageNameForInvalidTitle() {
		$output = $this->parse( '{{#invoke:DisplayTitleLuaTest|get|<invalid>}}' );

		$this->assertStringContainsString( '&lt;invalid&gt;', $output->getRawText() );
	}

	public function testGetWithEmptyNameReturnsEmptyString() {
		$output = $this->parse( 'x{{#invoke:DisplayTitleLuaTest|get|}}x' );

		$this->assertStringContainsString( '<p>xx', $output->getRawText() );
	}

	public function testSetChangesDisplayTitleOfCurrentPage() {
		$output = $this->parse( '{{#invoke:DisplayTitleLuaTest|set|Gnu}}' );

		$this->assertSame( 'Gnu', $output->getDisplayTitle() );
	}

	public function testSetWithEmptyTitleDoesNotChangeDisplayTitle() {
		$output = $this->parse( '{{#invoke:DisplayTitleLuaTest|set|}}' );

		$this->assertStringNotContainsString( 'Gnu', (string)$output->getDisplayTitle() );
	}
}
