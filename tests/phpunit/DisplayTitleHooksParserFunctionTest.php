<?php

declare( strict_types=1 );

use MediaWiki\MediaWikiServices;

/**
 * Tests for the #getdisplaytitle parser function and Scribunto integration.
 *
 * @group Database
 * @covers DisplayTitleHooks::onParserFirstCallInit
 * @covers DisplayTitleHooks::getdisplaytitleParserFunction
 * @covers DisplayTitleHooks::getDisplayTitle
 * @covers DisplayTitleHooks::onScribuntoExternalLibraries
 */
class DisplayTitleHooksParserFunctionTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( 'wgAllowDisplayTitle', true );
		$this->setMwGlobals( 'wgRestrictDisplayTitle', false );
	}

	public function testOnParserFirstCallInit(): void {
		$parser = MediaWikiServices::getInstance()->getParser();
		DisplayTitleHooks::onParserFirstCallInit( $parser );
		// Verifies re-registration of the function hook does not throw.
		$this->addToAssertionCount( 1 );
	}

	public function testGetdisplaytitleParserFunctionReturnsDisplayTitle(): void {
		$pageName = 'DTParserFuncTest01';
		$this->editPage( $pageName, '{{DISPLAYTITLE:My Custom Title}}' );
		Title::clearCaches();

		// $parser is not used by the method; a mock satisfies the type hint.
		$parser = $this->createMock( Parser::class );
		$result = DisplayTitleHooks::getdisplaytitleParserFunction( $parser, $pageName );

		$this->assertSame( 'My Custom Title', $result );
	}

	public function testGetdisplaytitleParserFunctionReturnsPagename(): void {
		$pageName = 'DTParserFuncTest02';
		$this->editPage( $pageName, 'Regular page content' );
		Title::clearCaches();

		$parser = $this->createMock( Parser::class );
		$result = DisplayTitleHooks::getdisplaytitleParserFunction( $parser, $pageName );

		$this->assertSame( $pageName, $result );
	}

	public function testOnScribuntoExternalLibrariesRegistersLuaLibrary(): void {
		$extraLibraries = [];
		DisplayTitleHooks::onScribuntoExternalLibraries( 'lua', $extraLibraries );

		$this->assertArrayHasKey( 'mw.ext.displaytitle', $extraLibraries );
		$this->assertSame( 'DisplayTitleLuaLibrary', $extraLibraries['mw.ext.displaytitle'] );
	}

	public function testOnScribuntoExternalLibrariesSkipsNonLua(): void {
		$extraLibraries = [];
		DisplayTitleHooks::onScribuntoExternalLibraries( 'javascript', $extraLibraries );

		$this->assertArrayNotHasKey( 'mw.ext.displaytitle', $extraLibraries );
	}

	public function testGetDisplayTitleFollowsRedirectToDisplayTitle(): void {
		// wgDisplayTitleFollowRedirects defaults to true; no override needed.
		$this->editPage( 'DTRedirectTargetTest01', '{{DISPLAYTITLE:Redirect Target Title}}' );
		$this->editPage( 'DTRedirectSourceTest01', '#REDIRECT [[DTRedirectTargetTest01]]' );
		Title::clearCaches();

		$parser = $this->createMock( Parser::class );
		$result = DisplayTitleHooks::getdisplaytitleParserFunction( $parser, 'DTRedirectSourceTest01' );

		$this->assertSame( 'Redirect Target Title', $result );
	}

}
