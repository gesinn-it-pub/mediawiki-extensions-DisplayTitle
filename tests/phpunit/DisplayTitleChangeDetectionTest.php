<?php

namespace MediaWiki\Extension\DisplayTitle\Tests;

use CommentStoreComment;
use MediaWikiIntegrationTestCase;
use ParserOptions;
use RequestContext;
use Title;
use WikitextContent;

/**
 * @covers \MediaWiki\Extension\DisplayTitle\DisplayTitleHooks::onParserAfterParse
 * @group Database
 */
class DisplayTitleChangeDetectionTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgAllowDisplayTitle' => true,
			'wgRestrictDisplayTitle' => false
		] );
		RequestContext::getMain()->setTitle( Title::newFromText( 'Main Page' ) );
	}

	private function createPage( string $titleText, string $wikitext ): void {
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( $titleText ) );
		$updater = $page->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( 'main', new WikitextContent( $wikitext ) );
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'new test page' ),
			EDIT_AUTOSUMMARY
		);
	}

	private function getExtensionData( string $titleText, string $wikitext ) {
		$title = Title::newFromText( $titleText );
		$parserOptions = new ParserOptions( $this->getTestSysop()->getUser() );
		$parserOutput = $this->getServiceContainer()->getContentRenderer()->getParserOutput(
			new WikitextContent( $wikitext ), $title, null, $parserOptions
		);
		return $parserOutput->getExtensionData( 'displayTitle' );
	}

	public function testChangedDisplayTitleIsFlagged() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );

		$this->assertSame(
			[ 'displayTitleChanged' => true ],
			$this->getExtensionData( 'Dingo Page', '{{DISPLAYTITLE:Gnu}}' )
		);
	}

	public function testUnchangedDisplayTitleIsNotFlagged() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );

		$this->assertNull( $this->getExtensionData( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' ) );
	}

	public function testPageWithoutDisplayTitleIsNotFlagged() {
		$this->createPage( 'Elephant Page', 'Content' );

		$this->assertNull( $this->getExtensionData( 'Elephant Page', 'Other content' ) );
	}
}
