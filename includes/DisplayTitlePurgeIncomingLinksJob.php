<?php

use GenericParameterJob;
use Job;
use Wikimedia\Rdbms\IDatabase;

class DisplayTitlePurgeIncomingLinksJob extends Job implements GenericParameterJob
{

    function __construct(array $params)
    {
        parent::__construct('DisplayTitlePurgeIncomingLinksJob', $params);
    }

    /**
     * Run the job
     * @return bool success
     */
    public function run()
    {
		$wikiPage = WikiPage::newFromID( $this->params['pageid'] );
        $incomingLinks = self::getIncomingLinks( $wikiPage->getTitle() );

		foreach ($incomingLinks as $row) {
            $incomingLinkPage = WikiPage::newFromID( $row->page_id );
            $incomingLinkPage->doPurge();
        }

        return true;
    }

	/**
	 * Get's all pages that link to the currently handled page.
	 * 
	 * @param Title $title
	 * @return array
	 */
	private static function getIncomingLinks($title)
	{

		$dbr = wfGetDB(DB_REPLICA);
		// Build query conds in concert for all four tables...
		$conds = [];
		$conds['redirect'] = [
			'rd_namespace' => $title->getNamespace(),
			'rd_title' => $title->getDBkey(),
		];
		$conds['pagelinks'] = [
			'pl_namespace' => $title->getNamespace(),
			'pl_title' => $title->getDBkey(),
		];
		$conds['templatelinks'] = [
			'tl_namespace' => $title->getNamespace(),
			'tl_title' => $title->getDBkey(),
		];
		$conds['imagelinks'] = [
			'il_to' => $title->getDBkey(),
		];

		$queryFunc = function (IDatabase $dbr, $table, $fromCol) use ($conds, $title) {
			// Read an extra row as an at-end check
			$on = [
				"rd_from = $fromCol",
				'rd_title' => $title->getDBkey(),
				'rd_interwiki = ' . $dbr->addQuotes('') . ' OR rd_interwiki IS NULL'
			];
			$on['rd_namespace'] = $title->getNamespace();
			// Inner LIMIT is 2X in case of stale backlinks with wrong namespaces
			$subQuery = $dbr->newSelectQueryBuilder()
				->table($table)
				->fields([$fromCol, 'rd_from', 'rd_fragment'])
				->conds($conds[$table])
				->orderBy($fromCol)
				->leftJoin('redirect', 'redirect', $on);

			return $dbr->newSelectQueryBuilder()
				->table($subQuery, 'temp_backlink_range')
				->join('page', 'page', "$fromCol = page_id")
				->fields([
					'page_id',
					'page_namespace',
					'page_title',
					'rd_from',
					'rd_fragment',
					'page_is_redirect'
				])
				->orderBy('page_id')
				->fetchResultSet();
		};
		$rdRes = $dbr->select(
			['redirect', 'page'],
			['page_id', 'page_namespace', 'page_title', 'rd_from', 'rd_fragment', 'page_is_redirect'],
			$conds['redirect'],
			__METHOD__,
			['ORDER BY' => 'rd_from'],
			['page' => ['JOIN', 'rd_from = page_id']]
		);
		$plRes = $queryFunc($dbr, 'pagelinks', 'pl_from');
		$tlRes = $queryFunc($dbr, 'templatelinks', 'tl_from');
		$ilRes = $queryFunc($dbr, 'imagelinks', 'il_from');

		$rows = [];

		foreach ($rdRes as $row) {
			$row->is_template = 0;
			$row->is_image = 0;
			$rows[$row->page_id] = $row;
		}

		foreach ($plRes as $row) {
			$row->is_template = 0;
			$row->is_image = 0;
			$rows[$row->page_id] = $row;
		}

		foreach ($tlRes as $row) {
			$row->is_template = 1;
			$row->is_image = 0;
			$rows[$row->page_id] = $row;
		}

		foreach ($ilRes as $row) {
			$row->is_template = 0;
			$row->is_image = 1;
			$rows[$row->page_id] = $row;
		}
		ksort($rows);
		return array_values($rows);
	}
}