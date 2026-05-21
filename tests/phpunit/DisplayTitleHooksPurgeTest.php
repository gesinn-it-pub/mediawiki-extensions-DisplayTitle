<?php

declare( strict_types=1 );

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RenderedRevision;

/**
 * Tests for the purge-on-display-title-change feature.
 *
 * Covers the detection of display title changes (onParserAfterParse) and
 * the subsequent job dispatch (onRevisionDataUpdates).
 *
 * @group Database
 * @covers DisplayTitleHooks::onParserAfterParse
 * @covers DisplayTitleHooks::onRevisionDataUpdates
 */
class DisplayTitleHooksPurgeTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgAllowDisplayTitle' => true,
			'wgRestrictDisplayTitle' => false,
		] );
	}

	public function testOnParserAfterParseSetsChangedFlagWhenTitleDiffers(): void {
		$this->editPage( 'DisplayTitlePurgeHooksTest01', '{{DISPLAYTITLE:Original Title}}' );
		$title = Title::newFromText( 'DisplayTitlePurgeHooksTest01' );

		$parser = MediaWikiServices::getInstance()->getParser();
		$output = $parser->parse( '{{DISPLAYTITLE:Changed Title}}', $title, ParserOptions::newFromAnon() );

		$data = $output->getExtensionData( 'displayTitle' );
		$this->assertNotNull( $data, 'Extension data should be set when display title changes' );
		$this->assertTrue( $data['displayTitleChanged'], 'displayTitleChanged should be true' );
	}

	public function testOnParserAfterParseDoesNotSetFlagWhenTitleUnchanged(): void {
		$this->editPage( 'DisplayTitlePurgeHooksTest02', '{{DISPLAYTITLE:Same Title}}' );
		$title = Title::newFromText( 'DisplayTitlePurgeHooksTest02' );

		$parser = MediaWikiServices::getInstance()->getParser();
		$output = $parser->parse( '{{DISPLAYTITLE:Same Title}}', $title, ParserOptions::newFromAnon() );

		$this->assertNull(
			$output->getExtensionData( 'displayTitle' ),
			'Extension data should not be set when display title is unchanged'
		);
	}

	public function testOnParserAfterParseDoesNotSetFlagWhenNeitherOldNorNewTitleIsSet(): void {
		$this->editPage( 'DisplayTitlePurgeHooksTest03', 'No display title here.' );
		$title = Title::newFromText( 'DisplayTitlePurgeHooksTest03' );

		$parser = MediaWikiServices::getInstance()->getParser();
		$output = $parser->parse( 'Still no display title.', $title, ParserOptions::newFromAnon() );

		$this->assertNull(
			$output->getExtensionData( 'displayTitle' ),
			'Extension data should not be set when neither old nor new title is set'
		);
	}

	public function testOnParserAfterParseSetsChangedFlagWhenTitleAddedForFirstTime(): void {
		$this->editPage( 'DisplayTitlePurgeHooksTest04', 'No display title yet.' );
		$title = Title::newFromText( 'DisplayTitlePurgeHooksTest04' );

		$parser = MediaWikiServices::getInstance()->getParser();
		$output = $parser->parse( '{{DISPLAYTITLE:Brand New Title}}', $title, ParserOptions::newFromAnon() );

		$data = $output->getExtensionData( 'displayTitle' );
		$this->assertNotNull( $data, 'Extension data should be set when display title is added for the first time' );
		$this->assertTrue( $data['displayTitleChanged'] );
	}

	public function testOnParserAfterParseSetsChangedFlagWhenTitleRemoved(): void {
		$this->editPage( 'DisplayTitlePurgeHooksTest05', '{{DISPLAYTITLE:Existing Title}}' );
		$title = Title::newFromText( 'DisplayTitlePurgeHooksTest05' );

		$parser = MediaWikiServices::getInstance()->getParser();
		$output = $parser->parse( 'Display title removed.', $title, ParserOptions::newFromAnon() );

		$data = $output->getExtensionData( 'displayTitle' );
		$this->assertNotNull( $data, 'Extension data should be set when display title is removed' );
		$this->assertTrue( $data['displayTitleChanged'] );
	}

	public function testOnRevisionDataUpdatesQueuesJobWhenTitleChanged(): void {
		$this->editPage( 'DisplayTitlePurgeHooksTest06', 'Content' );
		$title = Title::newFromText( 'DisplayTitlePurgeHooksTest06' );

		$parserOutput = new ParserOutput();
		$parserOutput->setExtensionData( 'displayTitle', [ 'displayTitleChanged' => true ] );

		$renderedRevision = $this->createMock( RenderedRevision::class );
		$renderedRevision->method( 'getRevisionParserOutput' )->willReturn( $parserOutput );

		$sizeBefore = JobQueueGroup::singleton()
			->get( 'DisplayTitlePurgeIncomingLinksJob' )
			->getSize();

		$hooks = new DisplayTitleHooks();
		$hooks->onRevisionDataUpdates( $title, $renderedRevision );

		$sizeAfter = JobQueueGroup::singleton()
			->get( 'DisplayTitlePurgeIncomingLinksJob' )
			->getSize();

		$this->assertGreaterThan( $sizeBefore, $sizeAfter, 'A purge job should have been queued' );
	}

	public function testOnRevisionDataUpdatesDoesNotQueueJobWhenFlagIsFalse(): void {
		$this->editPage( 'DisplayTitlePurgeHooksTest07', 'Content' );
		$title = Title::newFromText( 'DisplayTitlePurgeHooksTest07' );

		$parserOutput = new ParserOutput();
		$parserOutput->setExtensionData( 'displayTitle', [ 'displayTitleChanged' => false ] );

		$renderedRevision = $this->createMock( RenderedRevision::class );
		$renderedRevision->method( 'getRevisionParserOutput' )->willReturn( $parserOutput );

		$sizeBefore = JobQueueGroup::singleton()
			->get( 'DisplayTitlePurgeIncomingLinksJob' )
			->getSize();

		$hooks = new DisplayTitleHooks();
		$hooks->onRevisionDataUpdates( $title, $renderedRevision );

		$sizeAfter = JobQueueGroup::singleton()
			->get( 'DisplayTitlePurgeIncomingLinksJob' )
			->getSize();

		$this->assertSame( $sizeBefore, $sizeAfter, 'No job should be queued when displayTitleChanged is false' );
	}

	public function testOnRevisionDataUpdatesDoesNotQueueJobWhenNoExtensionData(): void {
		$this->editPage( 'DisplayTitlePurgeHooksTest08', 'Content' );
		$title = Title::newFromText( 'DisplayTitlePurgeHooksTest08' );

		$parserOutput = new ParserOutput();

		$renderedRevision = $this->createMock( RenderedRevision::class );
		$renderedRevision->method( 'getRevisionParserOutput' )->willReturn( $parserOutput );

		$sizeBefore = JobQueueGroup::singleton()
			->get( 'DisplayTitlePurgeIncomingLinksJob' )
			->getSize();

		$hooks = new DisplayTitleHooks();
		$hooks->onRevisionDataUpdates( $title, $renderedRevision );

		$sizeAfter = JobQueueGroup::singleton()
			->get( 'DisplayTitlePurgeIncomingLinksJob' )
			->getSize();

		$this->assertSame( $sizeBefore, $sizeAfter, 'No job should be queued when no extension data is present' );
	}

}
