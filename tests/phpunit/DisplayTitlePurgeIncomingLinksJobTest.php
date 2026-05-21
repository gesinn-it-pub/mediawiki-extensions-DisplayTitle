<?php

declare( strict_types=1 );

/**
 * Tests for DisplayTitlePurgeIncomingLinksJob.
 *
 * @group Database
 * @covers DisplayTitlePurgeIncomingLinksJob::run
 */
class DisplayTitlePurgeIncomingLinksJobTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgAllowDisplayTitle' => true,
			'wgRestrictDisplayTitle' => false,
		] );
	}

	public function testRunReturnsTrueForPageWithNoIncomingLinks(): void {
		$this->editPage( 'DisplayTitleJobTargetTest01', '{{DISPLAYTITLE:Job Target}}' );
		$pageId = Title::newFromText( 'DisplayTitleJobTargetTest01' )->getArticleID();

		$job = new DisplayTitlePurgeIncomingLinksJob( [ 'pageid' => $pageId ] );

		$this->assertTrue( $job->run() );
	}

	public function testRunReturnsTrueAndPurgesIncomingLinks(): void {
		$this->editPage( 'DisplayTitleJobTargetTest02', '{{DISPLAYTITLE:Linked Target}}' );
		$this->editPage( 'DisplayTitleJobLinkingTest02', '[[DisplayTitleJobTargetTest02]]' );

		$targetTitle = Title::newFromText( 'DisplayTitleJobTargetTest02' );
		$linkingTitle = Title::newFromText( 'DisplayTitleJobLinkingTest02' );

		$pageId = $targetTitle->getArticleID();
		$touchedBefore = $linkingTitle->getTouched();

		$job = new DisplayTitlePurgeIncomingLinksJob( [ 'pageid' => $pageId ] );
		$result = $job->run();

		$this->assertTrue( $result );

		// Reload title to get fresh touched timestamp
		$linkingTitle = Title::newFromText( 'DisplayTitleJobLinkingTest02' );
		$this->assertGreaterThanOrEqual(
			$touchedBefore,
			$linkingTitle->getTouched(),
			'Linking page should have been touched by the purge'
		);
	}

}
