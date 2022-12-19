<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use Wikimedia\AtEase\AtEase;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Pager for Special:GlobalContributions
 * @ingroup SpecialPage Pager
 */
class GlobalContribsPager extends ContribsPager {

	/**
	 * This method basically executes the exact same code as the parent class, though with
	 * a hook added, to allow extentions to add additional queries.
	 *
	 * @param string $offset index offset, inclusive
	 * @param int $limit exact query limit
	 * @param bool $descending query direction, false for ascending, true for descending
	 * @return IResultWrapper
	 */
	function reallyDoQuery( $offset, $limit, $descending ) {
		list( $tables, $fields, $conds, $fname, $options, $join_conds ) = $this->buildQueryInfo( $offset, $limit, $descending );
		$pager = $this;

		/*
		 * This hook will allow extensions to add in additional queries, so they can get their data
		 * in My Contributions as well. Extensions should append their results to the $data array.
		 *
		 * Extension queries have to implement the navbar requirement as well. They should
		 * - have a column aliased as $pager->getIndexField()
		 * - have LIMIT set
		 * - have a WHERE-clause that compares the $pager->getIndexField()-equivalent column to the offset
		 * - have the ORDER BY specified based upon the details provided by the navbar
		 *
		 * See includes/Pager.php buildQueryInfo() method on how to build LIMIT, WHERE & ORDER BY
		 *
		 * &$data: an array of results of all contribs queries
		 * $pager: the ContribsPager object hooked into
		 * $offset: see phpdoc above
		 * $limit: see phpdoc above
		 * $descending: see phpdoc above
		 */
		$data = [];
		global $wgConf;
		foreach ( $wgConf->wikis as $wiki ) {
			$dbr = wfGetDB( DB_REPLICA, [], $wiki );
			$thisData = $dbr->select( $tables, $fields, $conds, $fname, $options, $join_conds );
			$newData = [];
			foreach ( $thisData as $i => $row ) {
				// $dat[$i] -> wiki = $wiki;
				$row->wiki = $wiki;
				$newData[] = $row;
				// $newData[$i] -> wiki = $wiki;
			}
			$data[] = $newData;
		}
		// $data = array( GlobalDB::selectAll( $tables, $fields, $conds, $fname, $options, $join_conds ), );
		Hooks::run( 'GlobalContribsPager::reallyDoQuery', [ &$data, $pager, $offset, $limit, $descending ] );

		$result = [];

		// loop all results and collect them in an array
		foreach ( $data as $j => $query ) {
			foreach ( $query as $i => $row ) {
				// use index column as key, allowing us to easily sort in PHP
				$result[$row->{$this->getIndexField()} . "-$i"] = $row;
			}
		}

		// sort results
		if ( $descending ) {
			ksort( $result );
		} else {
			krsort( $result );
		}

		// enforce limit
		$result = array_slice( $result, 0, $limit );

		// get rid of array keys
		$result = array_values( $result );

		return new FakeResultWrapper( $result );
	}

	function getUserCond() {
		$condition = [];
		$join_conds = [];
		$tables = [ 'revision', 'page' ];

		$uid = User::idFromName( $this->target );
		if ( $uid ) {
			$condition['rev_user'] = $uid;
			$index = 'user_timestamp';
		} else {
			$condition['rev_user_text'] = $this->target;
			$index = 'usertext_timestamp';
		}

		if ( $this->deletedOnly ) {
			$condition[] = "rev_deleted != '0'";
		}
		if ( $this->topOnly ) {
			$condition[] = 'rev_id = page_latest';
		}

		return [ $tables, $index, $condition, $join_conds ];
	}

	function getQueryInfo() {
		list( $tables, $index, $userCond, $join_cond ) = $this->getUserCond();

		$user = $this->getUser();
		$conds = array_merge( $userCond, $this->getNamespaceCond() );

		// Paranoia: avoid brute force searches (bug 17342)
		if ( !$user->isAllowed( 'deletedhistory' ) ) {
			$conds[] = $this->mDb->bitAnd( 'rev_deleted', Revision::DELETED_USER ) . ' = 0';
		} elseif ( !$user->isAllowed( 'suppressrevision' ) ) {
			$conds[] = $this->mDb->bitAnd( 'rev_deleted', Revision::SUPPRESSED_USER ) .
				' != ' . Revision::SUPPRESSED_USER;
		}

		# Don't include orphaned revisions
		$join_cond['page'] = Revision::pageJoinCond();
		# Get the current user name for accounts
		$join_cond['user'] = Revision::userJoinCond();

		$options = [];
		if ( $index ) {
			$options['USE INDEX'] = [ 'revision' => $index ];
		}

		$queryInfo = [
			'tables' => $tables,
			'fields' => array_merge(
				Revision::selectFields(),
				[ 'page_namespace', 'page_title', 'page_is_new',
					'page_latest', 'page_is_redirect', 'page_len' ]
			),
			'conds' => $conds,
			'options' => $options,
			'join_conds' => $join_cond
		];

		ChangeTags::modifyDisplayQuery(
			$queryInfo['tables'],
			$queryInfo['fields'],
			$queryInfo['conds'],
			$queryInfo['join_conds'],
			$queryInfo['options'],
			$this->tagFilter
		);

		// Avoid PHP 7.1 warning of passing $this by reference
		$globalContribsPager = $this;
		Hooks::run( 'ContribsPager::getQueryInfo', [ &$globalContribsPager, &$queryInfo ] );

		return $queryInfo;
	}

	function doBatchLookups() {
		global $wgConf;

		# Do a link batch query
		$this->mResult->seek( 0 );
		$revIds = [];
		$batch = MediaWikiServices::getInstance()->getLinkBatchFactory()->newLinkBatch();

		# Give some pointers to make (last) links
		foreach ( $this->mResult as $row ) {
			if ( isset( $row->rev_parent_id ) && $row->rev_parent_id ) {
				$revIds[] = $row->rev_parent_id;
			}
			if ( isset( $row->rev_id ) ) {
				$batch->add( $row->page_namespace, $row->page_title );
			}
		}

		$factory = MediaWikiServices::getInstance()->getRevisionStoreFactory();
		foreach ( $wgConf->wikis as $wiki ) {
			$this->mParentLensArr[$wiki] = $factory->getRevisionStore( $wiki )
				->getRevisionSizes( $revIds );
		}

		$batch->execute();
		$this->mResult->seek( 0 );
	}

	function formatRow( $row ) {
		global $wgConf;

		$ret = '';
		$classes = [];

		/*
		 * There may be more than just revision rows. To make sure that we'll only be processing
		 * revisions here, let's _try_ to build a revision out of our row (without displaying
		 * notices though) and then trying to grab data from the built object. If we succeed,
		 * we're definitely dealing with revision data and we may proceed, if not, we'll leave it
		 * to extensions to subscribe to the hook to parse the row.
		 */
		AtEase::suppressWarnings();
		$rev = new Revision( $row );
		$validRevision = $rev->getParentId() !== null;
		AtEase::restoreWarnings();

		if ( $validRevision ) {
			$classes = [];

			$page = Title::newFromRow( $row );

			$url = $wgConf->get( 'wgServer', $row->wiki ) . str_replace( '$1', $page->getFullText(), $wgConf->get( 'wgArticlePath', $row->wiki ) );

			$link = Html::element(
				'a',
				[
					'href' => $url,
					'class' => 'mw-contributions-title',
				],
				$page->getPrefixedText()
			);

			# Mark current revisions
			$topmarktext = '';
			$user = $this->getUser();
			if ( $row->rev_id == $row->page_latest ) {
				$topmarktext .= '<span class="mw-uctop">' . $this->messages['uctop'] . '</span>';
				# Add rollback link
				$permManager = MediaWikiServices::getInstance()->getPermissionManager();
				$quickUserCan = $permManager->quickUserCan( 'rollback', $user, $page ) &&
					$permManager->quickUserCan( 'edit', $user, $page );
				if ( !$row->page_is_new && $quickUserCan ) {
					$this->preventClickjacking();
					$topmarktext .= ' ' . Linker::generateRollback( $rev, $this->getContext() );
				}
			}
			# Is there a visible previous revision?
			if ( $rev->getParentId() !== 0 &&
				RevisionRecord::userCanBitfield(
					$rev->getVisibility(),
					RevisionRecord::DELETED_TEXT,
					$user
				)
			) {
				$difftext = Html::element(
					'a',
					[
						'href' => $url . '?diff=prev&oldid=' . $row->rev_id,
					],
					$this->messages['diff']
				);
			} else {
				$difftext = $this->messages['diff'];
			}
			$histlink = Html::element(
				'a',
				[
					'href' => $url . '?action=history',
				],
				$this->messages['hist']
			);

			if ( $row->rev_parent_id === null ) {
				// For some reason rev_parent_id isn't populated for this row.
				// Its rumoured this is true on wikipedia for some revisions (bug 34922).
				// Next best thing is to have the total number of bytes.
				$chardiff = ' <span class="mw-changeslist-separator">. .</span> ' . Linker::formatRevisionSize( $row->rev_len ) . ' <span class="mw-changeslist-separator">. .</span> ';
			} else {
				$parentLen = $this->mParentLensArr[$row->wiki][$row->rev_parent_id] ?? 0;
				$chardiff = ' <span class="mw-changeslist-separator">. .</span> ' . ChangesList::showCharacterDifference(
					$parentLen, $row->rev_len, $this->getContext() ) . ' <span class="mw-changeslist-separator">. .</span> ';
			}

			$lang = $this->getLanguage();
			$comment = $lang->getDirMark() . Linker::revComment( $rev, false, true );
			$date = $lang->userTimeAndDate( $row->rev_timestamp, $user );
			if ( RevisionRecord::userCanBitfield(
				$rev->getVisibility(),
				RevisionRecord::DELETED_TEXT,
				$user
			) ) {
				$d = Html::element(
					'a',
					[
						'href' => $url . '?oldid=' . intval( $row->rev_id ),
						'class' => 'mw-changeslist-date',
					],
					$date
				);
			} else {
				$d = htmlspecialchars( $date );
			}
			if ( $rev->isDeleted( Revision::DELETED_TEXT ) ) {
				$d = '<span class="history-deleted">' . $d . '</span>';
			}

			# Show user names for /newbies as there may be different users.
			# Note that we already excluded rows with hidden user names.
			if ( $this->contribs == 'newbie' ) {
				$userlink = ' . . ' . Linker::userLink( $rev->getUser(), $rev->getUserText() );
				$userlink .= ' ' . $this->msg( 'parentheses' )->rawParams(
					Linker::userTalkLink( $rev->getUser(), $rev->getUserText() ) )->escaped() . ' ';
			} else {
				$userlink = '';
			}

			if ( $rev->getParentId() === 0 ) {
				$nflag = ChangesList::flag( 'newpage' );
			} else {
				$nflag = '';
			}

			if ( $rev->isMinor() ) {
				$mflag = ChangesList::flag( 'minor' );
			} else {
				$mflag = '';
			}

			$diffHistLinks = $this->msg( 'parentheses' )->rawParams( $difftext . $this->messages['pipe-separator'] . $histlink )->escaped();
			$ret = "{$d} {$diffHistLinks}{$chardiff}{$nflag}{$mflag} {$link}{$userlink} {$comment} {$topmarktext}";

			# Denote if username is redacted for this edit
			if ( $rev->isDeleted( Revision::DELETED_USER ) ) {
				$ret .= ' <strong>' . $this->msg( 'rev-deleted-user-contribs' )->escaped() . '</strong>';
			}

			# Tags, if any.
			//list( $tagSummary, $newClasses ) = ChangeTags::formatSummaryRow( $row->ts_tags, 'contributions' );
			//$classes = array_merge( $classes, $newClasses );
			//$ret .= " $tagSummary";
		}

		// Let extensions add data
		Hooks::run( 'ContributionsLineEnding', [ $this, &$ret, $row, &$classes ] );

		$wiki = "<span class='gc-wiki'>{$row->wiki} - </span>";

		$classes = implode( ' ', $classes );
		$ret = "<li class=\"$classes\">$wiki$ret</li>\n";

		return $ret;
	}

}
