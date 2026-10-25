<?php

namespace MediaWiki\Extension\DisplayTitle;

use GenericParameterJob;
use HTMLCacheUpdateJob;
use Job;
use MediaWiki\MediaWikiServices;
use Title;

/**
 * Job to invalidate the cached output of all pages that show the display title
 * of a page whose display title has changed. This ensures that cached link text
 * and category listings are refreshed.
 *
 * Besides pages that link to, transclude or embed the changed page, this covers
 * pages that link to a redirect to the changed page (the link text follows the
 * redirect) and the categories the changed page is a member of.
 *
 * @since 1.6
 */
class DisplayTitlePurgeIncomingLinksJob extends Job implements GenericParameterJob {

	private const LINK_TABLES = [ 'pagelinks', 'templatelinks', 'imagelinks' ];

	private const REDIRECT_LINK_TABLES = [ 'pagelinks', 'templatelinks' ];

	/**
	 * @param array $params Job parameters; must contain 'pageid'
	 */
	public function __construct( array $params ) {
		parent::__construct( 'DisplayTitlePurgeIncomingLinksJob', $params );
	}

	/**
	 * Run the job: queue the invalidation of all pages affected by the changed display title.
	 *
	 * @return bool success
	 */
	public function run(): bool {
		$title = Title::newFromID( $this->params['pageid'] );
		if ( $title === null ) {
			return true;
		}

		$services = MediaWikiServices::getInstance();
		$backlinkCache = $services->getBacklinkCacheFactory()->getBacklinkCache( $title );

		$jobs = [];
		foreach ( self::LINK_TABLES as $table ) {
			$jobs[] = HTMLCacheUpdateJob::newForBacklinks( $title, $table );
		}

		// Links to a redirect show the display title of its target
		foreach ( $backlinkCache->getLinkPages( 'redirect' ) as $redirectPage ) {
			$redirect = Title::castFromPageIdentity( $redirectPage );
			if ( $redirect === null ) {
				continue;
			}
			$redirect->invalidateCache();
			foreach ( self::REDIRECT_LINK_TABLES as $table ) {
				$jobs[] = HTMLCacheUpdateJob::newForBacklinks( $redirect, $table );
			}
		}

		// Category pages list their members by display title
		foreach ( array_keys( $title->getParentCategories() ) as $categoryText ) {
			$category = Title::newFromText( $categoryText );
			if ( $category !== null ) {
				$category->invalidateCache();
			}
		}

		$services->getJobQueueGroup()->push( $jobs );

		return true;
	}
}
