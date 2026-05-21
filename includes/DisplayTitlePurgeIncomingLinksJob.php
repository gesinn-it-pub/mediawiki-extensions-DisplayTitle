<?php

namespace MediaWiki\Extension\DisplayTitle;

use GenericParameterJob;
use Job;
use MediaWiki\MediaWikiServices;
use Title;

/**
 * Job to purge the parser cache of all pages that link to a page whose
 * display title has changed. This ensures that cached link text is refreshed.
 *
 * @since 1.6
 */
class DisplayTitlePurgeIncomingLinksJob extends Job implements GenericParameterJob {

	/**
	 * @param array $params Job parameters; must contain 'pageid'
	 */
	public function __construct( array $params ) {
		parent::__construct( 'DisplayTitlePurgeIncomingLinksJob', $params );
	}

	/**
	 * Run the job: purge all pages with incoming links to the changed page.
	 *
	 * @return bool success
	 */
	public function run(): bool {
		$title = Title::newFromID( $this->params['pageid'] );
		if ( $title === null ) {
			return true;
		}

		$services = MediaWikiServices::getInstance();
		$wikiPageFactory = $services->getWikiPageFactory();
		$backlinkCacheFactory = $services->getBacklinkCacheFactory();
		$backlinkCache = $backlinkCacheFactory->getBacklinkCache( $title );

		$purged = [];
		foreach ( [ 'pagelinks', 'templatelinks', 'imagelinks' ] as $table ) {
			foreach ( $backlinkCache->getLinks( $table ) as $linkedTitle ) {
				$pageId = $linkedTitle->getArticleID();
				if ( !isset( $purged[$pageId] ) ) {
					$wikiPageFactory->newFromTitle( $linkedTitle )->doPurge();
					$purged[$pageId] = true;
				}
			}
		}

		return true;
	}
}
