<?php

declare( strict_types=1 );

use MediaWiki\MediaWikiServices;

/**
 * Tests for core DisplayTitle hook handlers.
 *
 * @group Database
 * @covers DisplayTitleHooks::onBeforePageDisplay
 * @covers DisplayTitleHooks::onParserBeforeInternalParse
 */
class DisplayTitleHooksCoreTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgAllowDisplayTitle' => true,
			'wgRestrictDisplayTitle' => false,
			'wgDisplayTitleHideSubtitle' => false,
		] );
	}

	public function testOnBeforePageDisplaySetsSubtitleWhenDisplayTitleIsSet(): void {
		$this->editPage( 'DisplayTitleCoreTest01', '{{DISPLAYTITLE:My Custom Title}}' );
		$title = Title::newFromText( 'DisplayTitleCoreTest01' );

		$context = new RequestContext();
		$context->setTitle( $title );
		$out = new OutputPage( $context );
		$out->setTitle( $title );
		$sk = $this->createMock( Skin::class );

		DisplayTitleHooks::onBeforePageDisplay( $out, $sk );

		$this->assertStringContainsString(
			$title->getPrefixedText(),
			$out->getSubtitle(),
			'Subtitle should contain the prefixed page name when display title is set'
		);
	}

	public function testOnBeforePageDisplayDoesNotSetSubtitleWhenNoDisplayTitle(): void {
		$this->editPage( 'DisplayTitleCoreTest02', 'No display title on this page.' );
		$title = Title::newFromText( 'DisplayTitleCoreTest02' );

		$context = new RequestContext();
		$context->setTitle( $title );
		$out = new OutputPage( $context );
		$out->setTitle( $title );
		$sk = $this->createMock( Skin::class );

		DisplayTitleHooks::onBeforePageDisplay( $out, $sk );

		$this->assertSame(
			'',
			$out->getSubtitle(),
			'Subtitle should remain empty when no display title is set'
		);
	}

	public function testOnBeforePageDisplayDoesNotSetSubtitleWhenHideSubtitleEnabled(): void {
		$this->setMwGlobals( 'wgDisplayTitleHideSubtitle', true );
		$this->editPage( 'DisplayTitleCoreTest03', '{{DISPLAYTITLE:Hidden Subtitle Test}}' );
		$title = Title::newFromText( 'DisplayTitleCoreTest03' );

		$context = new RequestContext();
		$context->setTitle( $title );
		$out = new OutputPage( $context );
		$out->setTitle( $title );
		$sk = $this->createMock( Skin::class );

		DisplayTitleHooks::onBeforePageDisplay( $out, $sk );

		$this->assertSame(
			'',
			$out->getSubtitle(),
			'Subtitle should not be set when wgDisplayTitleHideSubtitle is true'
		);
	}

	public function testOnBeforePageDisplaySetsTalkPageSubtitleFromSubjectPage(): void {
		$this->editPage( 'DisplayTitleCoreTest04', '{{DISPLAYTITLE:Subject Display Title}}' );
		$talkTitle = Title::newFromText( 'Talk:DisplayTitleCoreTest04' );

		$context = new RequestContext();
		$context->setTitle( $talkTitle );
		$out = new OutputPage( $context );
		$out->setTitle( $talkTitle );
		$sk = $this->createMock( Skin::class );

		DisplayTitleHooks::onBeforePageDisplay( $out, $sk );

		$this->assertStringContainsString(
			'DisplayTitleCoreTest04',
			$out->getSubtitle(),
			'Talk page subtitle should reference the subject page name'
		);
	}

	public function testOnParserBeforeInternalParseSetsDiscussTitleForTalkPage(): void {
		$this->editPage( 'DisplayTitleCoreTest05', '{{DISPLAYTITLE:Talk Subject Title}}' );
		$talkTitle = Title::newFromText( 'Talk:DisplayTitleCoreTest05' );

		$parser = MediaWikiServices::getInstance()->getParser();
		$output = $parser->parse( 'Talk page content.', $talkTitle, ParserOptions::newFromAnon() );

		$this->assertStringContainsString(
			'Talk Subject Title',
			$output->getTitleText(),
			'Talk page title text should contain the subject page display title'
		);
	}

	public function testOnParserBeforeInternalParseDoesNotSetTitleForNonTalkPage(): void {
		$this->editPage( 'DisplayTitleCoreTest06', '{{DISPLAYTITLE:Regular Page}}' );
		$title = Title::newFromText( 'DisplayTitleCoreTest06' );

		$parser = MediaWikiServices::getInstance()->getParser();
		$output = $parser->parse( 'Regular content.', $title, ParserOptions::newFromAnon() );

		// Non-talk pages are not processed by onParserBeforeInternalParse
		// The title text is not set by this hook for regular pages
		$this->assertStringNotContainsString(
			'Discuss',
			$output->getTitleText(),
			'Regular page title text should not have the Discuss prefix'
		);
	}

}
