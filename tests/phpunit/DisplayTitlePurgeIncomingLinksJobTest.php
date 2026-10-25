<?php

namespace MediaWiki\Extension\DisplayTitle\Tests;

use CommentStoreComment;
use MediaWiki\Extension\DisplayTitle\DisplayTitlePurgeIncomingLinksJob;
use MediaWikiIntegrationTestCase;
use RequestContext;
use Title;
use WikitextContent;

/**
 * @covers \MediaWiki\Extension\DisplayTitle\DisplayTitlePurgeIncomingLinksJob
 * @group Database
 */
class DisplayTitlePurgeIncomingLinksJobTest extends MediaWikiIntegrationTestCase {

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

	private function setTouched( string $titleText, string $timestamp ): void {
		$title = Title::newFromText( $titleText );
		$this->db->update(
			'page',
			[ 'page_touched' => $this->db->timestamp( $timestamp ) ],
			[ 'page_namespace' => $title->getNamespace(), 'page_title' => $title->getDBkey() ],
			__METHOD__
		);
	}

	private function getTouched( string $titleText ): string {
		return $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( $titleText ) )->getTouched();
	}

	private function runPurgeJob( string $titleText ): void {
		$job = new DisplayTitlePurgeIncomingLinksJob(
			[ 'pageid' => Title::newFromText( $titleText )->getArticleID() ]
		);
		$this->assertTrue( $job->run() );
		$this->runJobs();
	}

	public function testInvalidatesPagesLinkingToThePage() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$this->createPage( 'Linking Page', '[[Dingo Page]]' );
		$this->setTouched( 'Linking Page', '20200101000000' );

		$this->runPurgeJob( 'Dingo Page' );

		$this->assertGreaterThan( '20200101000000', $this->getTouched( 'Linking Page' ) );
	}

	public function testInvalidatesPagesLinkingViaRedirect() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$this->createPage( 'Redirect To Dingo Page', '#REDIRECT [[Dingo Page]]' );
		$this->createPage( 'Linking Page', '[[Redirect To Dingo Page]]' );
		$this->setTouched( 'Linking Page', '20200101000000' );

		$this->runPurgeJob( 'Dingo Page' );

		$this->assertGreaterThan( '20200101000000', $this->getTouched( 'Linking Page' ) );
	}

	public function testInvalidatesCategoriesOfThePage() {
		$this->createPage( 'Category:Animals', 'Animals' );
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}[[Category:Animals]]' );
		$this->setTouched( 'Category:Animals', '20200101000000' );

		$this->runPurgeJob( 'Dingo Page' );

		$this->assertGreaterThan( '20200101000000', $this->getTouched( 'Category:Animals' ) );
	}

	public function testLeavesUnrelatedPagesAlone() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$this->createPage( 'Unrelated Page', 'Content' );
		$this->setTouched( 'Unrelated Page', '20200101000000' );

		$this->runPurgeJob( 'Dingo Page' );

		$this->assertSame( '20200101000000', $this->getTouched( 'Unrelated Page' ) );
	}

	public function testSucceedsForMissingPage() {
		$job = new DisplayTitlePurgeIncomingLinksJob( [ 'pageid' => 999999999 ] );

		$this->assertTrue( $job->run() );
	}
}
