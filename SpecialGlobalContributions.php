<?php

/**
 * Implements Special:GlobalContributions
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 */

use MediaWiki\MediaWikiServices;

/**
 * Special:GlobalContributions, show user contributions in a paged list
 *
 * @ingroup SpecialPage
 */
class SpecialGlobalContributions extends SpecialContributions {

	public function getDescription() {
		return $this->msg( 'globalcontribs' )->escaped();
	}

	public function __construct() {
		SpecialPage::__construct( 'GlobalContributions' );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();

		$out = $this->getOutput();
		$out->addModuleStyles( 'mediawiki.special' );

		$this->opts = array();
		$request = $this->getRequest();

		if ( $par !== null ) {
			$target = $par;
		} else {
			$target = $request->getVal( 'target' );
		}

		// check for radiobox
		if ( $request->getVal( 'contribs' ) == 'newbie' ) {
			$target = 'newbies';
			$this->opts['contribs'] = 'newbie';
		} elseif ( $par === 'newbies' ) { // b/c for WMF
			$target = 'newbies';
			$this->opts['contribs'] = 'newbie';
		} else {
			$this->opts['contribs'] = 'user';
		}

		$this->opts['deletedOnly'] = $request->getBool( 'deletedOnly' );

		if ( !strlen( $target ) ) {
			$out->addHTML( $this->getForm() );
			return;
		}

		$user = $this->getUser();

		$this->opts['limit'] = $request->getInt( 'limit', $user->getOption( 'rclimit' ) );
		$this->opts['target'] = $target;
		$this->opts['topOnly'] = $request->getBool( 'topOnly' );

		$nt = Title::makeTitleSafe( NS_USER, $target );
		if ( !$nt ) {
			$out->addHTML( $this->getForm() );
			return;
		}
		$userObj = User::newFromName( $nt->getText(), false );
		if ( !$userObj ) {
			$out->addHTML( $this->getForm() );
			return;
		}
		$id = $userObj->getID();

		if ( $this->opts['contribs'] != 'newbie' ) {
			$target = $nt->getText();
			$out->addSubtitle( $this->contributionsSub( $userObj ) );
			$out->setHTMLTitle( $this->msg( 'pagetitle', $this->msg( 'contributions-title', $target )->plain() ) );
			$this->getSkin()->setRelevantUser( $userObj );
		} else {
			$out->addSubtitle( $this->msg( 'sp-contributions-newbies-sub' ) );
			$out->setHTMLTitle( $this->msg( 'pagetitle', $this->msg( 'sp-contributions-newbies-title' )->plain() ) );
		}

		if ( ( $ns = $request->getVal( 'namespace', null ) ) !== null && $ns !== '' ) {
			$this->opts['namespace'] = intval( $ns );
		} else {
			$this->opts['namespace'] = '';
		}

		$this->opts['associated'] = $request->getBool( 'associated' );

		$this->opts['nsInvert'] = (bool) $request->getVal( 'nsInvert' );

		$this->opts['tagfilter'] = (string) $request->getVal( 'tagfilter' );

		// Allows reverts to have the bot flag in recent changes. It is just here to
		// be passed in the form at the top of the page
		if ( $user->isAllowed( 'markbotedits' ) && $request->getBool( 'bot' ) ) {
			$this->opts['bot'] = '1';
		}

		$skip = $request->getText( 'offset' ) || $request->getText( 'dir' ) == 'prev';
		# Offset overrides year/month selection
		if ( $skip ) {
			$this->opts['year'] = '';
			$this->opts['month'] = '';
		} else {
			$this->opts['year'] = $request->getIntOrNull( 'year' );
			$this->opts['month'] = $request->getIntOrNull( 'month' );
		}

		$feedType = $request->getVal( 'feed' );
		if ( $feedType ) {
			// Maintain some level of backwards compatability
			// If people request feeds using the old parameters, redirect to API
			$apiParams = array(
				'action' => 'feedcontributions',
				'feedformat' => $feedType,
				'user' => $target,
			);
			if ( $this->opts['topOnly'] ) {
				$apiParams['toponly'] = true;
			}
			if ( $this->opts['deletedOnly'] ) {
				$apiParams['deletedonly'] = true;
			}
			if ( $this->opts['tagfilter'] !== '' ) {
				$apiParams['tagfilter'] = $this->opts['tagfilter'];
			}
			if ( $this->opts['namespace'] !== '' ) {
				$apiParams['namespace'] = $this->opts['namespace'];
			}
			if ( $this->opts['year'] !== null ) {
				$apiParams['year'] = $this->opts['year'];
			}
			if ( $this->opts['month'] !== null ) {
				$apiParams['month'] = $this->opts['month'];
			}

			$url = wfScript( 'api' ) . '?' . wfArrayToCGI( $apiParams );

			$out->redirect( $url, '301' );
			return;
		}

		// Add RSS/atom links
		$this->addFeedLinks( array( 'action' => 'feedcontributions', 'user' => $target ) );

		if ( Hooks::run( 'SpecialContributionsBeforeMainOutput', array( $id ) ) ) {
			$out->addHTML( $this->getForm() );

			$pager = new GlobalContribsPager( $this->getContext(), array(
				'target' => $target,
				'contribs' => $this->opts['contribs'],
				'namespace' => $this->opts['namespace'],
				'year' => $this->opts['year'],
				'month' => $this->opts['month'],
				'deletedOnly' => $this->opts['deletedOnly'],
				'topOnly' => $this->opts['topOnly'],
				'nsInvert' => $this->opts['nsInvert'],
				'associated' => $this->opts['associated'],
			) );
			if ( !$pager->getNumRows() ) {
				$out->addWikiMsg( 'nocontribs', $target );
			} else {
				# Show a message about slave lag, if applicable
				$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
				$lag = $lb->safeGetLag( $pager->getDatabase() );
				if ( $lag > 0 ) {
					$out->showLagWarning( $lag );
				}

				$out->addHTML(
					'<p>' . $pager->getNavigationBar() . '</p>' .
					$pager->getBody() .
					'<p>' . $pager->getNavigationBar() . '</p>'
				);
			}
			$out->preventClickjacking( $pager->getPreventClickjacking() );


			# Show the appropriate "footer" message - WHOIS tools, etc.
			if ( $this->opts['contribs'] == 'newbie' ) {
				$message = 'sp-contributions-footer-newbies';
			} elseif ( IP::isIPAddress( $target ) ) {
				$message = 'sp-contributions-footer-anon';
			} elseif ( $userObj->isAnon() ) {
				// No message for non-existing users
				$message = '';
			} else {
				$message = 'sp-contributions-footer';
			}

			if ( $message ) {
				if ( !$this->msg( $message, $target )->isDisabled() ) {
					$out->wrapWikiMsg(
						"<footer class='mw-contributions-footer'>\n$1\n</footer>",
						array( $message, $target )
					);
				}
			}
		}
	}
}

/**
 * Pager for Special:GlobalContributions
 * @ingroup SpecialPage Pager
 */
class GlobalContribsPager extends ContribsPager {

	/**
	 * This method basically executes the exact same code as the parent class, though with
	 * a hook added, to allow extentions to add additional queries.
	 *
	 * @param $offset String: index offset, inclusive
	 * @param $limit Integer: exact query limit
	 * @param $descending Boolean: query direction, false for ascending, true for descending
	 * @return ResultWrapper
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
		$data = array();
		global $wgConf;
		foreach ( $wgConf->wikis as $wiki ) {
			$dbr = wfGetDB( DB_REPLICA, array(), $wiki );
			$thisData = $dbr->select( $tables, $fields, $conds, $fname, $options, $join_conds );
			$newData = array();
			foreach ( $thisData as $i => $row ) {
				//$dat[$i] -> wiki = $wiki;
				$row->wiki = $wiki;
				$newData[] = $row;
				//$newData[$i] -> wiki = $wiki;
			}
			$data[] = $newData;
		}
		//$data = array( GlobalDB::selectAll( $tables, $fields, $conds, $fname, $options, $join_conds ), );
		Hooks::run( 'GlobalContribsPager::reallyDoQuery', array( &$data, $pager, $offset, $limit, $descending ) );

		$result = array();

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
		$condition = array();
		$join_conds = array();
		$tables = array( 'revision', 'page' );

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

		return array( $tables, $index, $condition, $join_conds );
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

		$options = array();
		if ( $index ) {
			$options['USE INDEX'] = array( 'revision' => $index );
		}

		$queryInfo = array(
			'tables' => $tables,
			'fields' => array_merge(
				Revision::selectFields(),
				array( 'page_namespace', 'page_title', 'page_is_new',
					'page_latest', 'page_is_redirect', 'page_len' )
			),
			'conds' => $conds,
			'options' => $options,
			'join_conds' => $join_cond
		);

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
		Hooks::run( 'ContribsPager::getQueryInfo', array( &$globalContribsPager, &$queryInfo ) );

		return $queryInfo;
	}

	function doBatchLookups() {
		global $wgConf;

		# Do a link batch query
		$this->mResult->seek( 0 );
		$revIds = array();
		$batch = new LinkBatch();

		# Give some pointers to make (last) links
		foreach ( $this->mResult as $row ) {
			if ( isset( $row->rev_parent_id ) && $row->rev_parent_id ) {
				$revIds[] = $row->rev_parent_id;
			}
			if ( isset( $row->rev_id ) ) {
				$batch->add( $row->page_namespace, $row->page_title );
			}
		}

		foreach ( $wgConf->wikis as $wiki ) {
			$this->mParentLensArr[$wiki] = Revision::getParentLengths( wfGetDB( DB_REPLICA, array(), $wiki ), $revIds );
		}

		$batch->execute();
		$this->mResult->seek( 0 );
	}

	function formatRow( $row ) {
		global $wgConf;

		$ret = '';
		$classes = array();

		/*
		 * There may be more than just revision rows. To make sure that we'll only be processing
		 * revisions here, let's _try_ to build a revision out of our row (without displaying
		 * notices though) and then trying to grab data from the built object. If we succeed,
		 * we're definitely dealing with revision data and we may proceed, if not, we'll leave it
		 * to extensions to subscribe to the hook to parse the row.
		 */
		Wikimedia\suppressWarnings();
		$rev = new Revision( $row );
		$validRevision = $rev->getParentId() !== null;
		Wikimedia\restoreWarnings();

		if ( $validRevision ) {
			$classes = array();

			$page = Title::newFromRow( $row );

			$url = $wgConf->get( 'wgServer', $row->wiki ) . str_replace( '$1', $page->getFullText(), $wgConf->get( 'wgArticlePath', $row->wiki ) );

			$link = Html::element(
				'a',
				array(
					'href' => $url,
					'class' => 'mw-contributions-title',
				),
				htmlspecialchars( $page->getPrefixedText() )
			);

			# Mark current revisions
			$topmarktext = '';
			$user = $this->getUser();
			if ( $row->rev_id == $row->page_latest ) {
				$topmarktext .= '<span class="mw-uctop">' . $this->messages['uctop'] . '</span>';
				# Add rollback link
				if (
					!$row->page_is_new &&
					$page->quickUserCan( 'rollback', $user ) &&
					$page->quickUserCan( 'edit', $user )
				)
				{
					$this->preventClickjacking();
					$topmarktext .= ' ' . Linker::generateRollback( $rev, $this->getContext() );
				}
			}
			# Is there a visible previous revision?
			if ( $rev->userCan( Revision::DELETED_TEXT, $user ) && $rev->getParentId() !== 0 ) {
				$difftext = Html::element(
					'a',
					array(
						'href' => $url . '?diff=prev&oldid=' . $row->rev_id,
					),
					$this->messages['diff']
				);
			} else {
				$difftext = $this->messages['diff'];
			}
			$histlink = Html::element(
				'a',
				array(
					'href' => $url . '?action=history',
				),
				$this->messages['hist']
			);

			if ( $row->rev_parent_id === null ) {
				// For some reason rev_parent_id isn't populated for this row.
				// Its rumoured this is true on wikipedia for some revisions (bug 34922).
				// Next best thing is to have the total number of bytes.
				$chardiff = ' <span class="mw-changeslist-separator">. .</span> ' . Linker::formatRevisionSize( $row->rev_len ) . ' <span class="mw-changeslist-separator">. .</span> ';
			} else {
				$parentLen = isset( $this->mParentLensArr[$row->wiki][$row->rev_parent_id] ) ? $this->mParentLensArr[$row->wiki][$row->rev_parent_id] : 0;
				$chardiff = ' <span class="mw-changeslist-separator">. .</span> ' . ChangesList::showCharacterDifference(
					$parentLen, $row->rev_len, $this->getContext() ) . ' <span class="mw-changeslist-separator">. .</span> ';
			}

			$lang = $this->getLanguage();
			$comment = $lang->getDirMark() . Linker::revComment( $rev, false, true );
			$date = $lang->userTimeAndDate( $row->rev_timestamp, $user );
			if ( $rev->userCan( Revision::DELETED_TEXT, $user ) ) {
				$d = Html::element(
					'a',
					array(
						'href' => $url . '?oldid=' . intval( $row->rev_id ),
						'class' => 'mw-changeslist-date',
					),
					htmlspecialchars( $date )
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
		Hooks::run( 'ContributionsLineEnding', array( $this, &$ret, $row, &$classes ) );

		$wiki = "<span class='gc-wiki'>{$row->wiki} - </span>";

		$classes = implode( ' ', $classes );
		$ret = "<li class=\"$classes\">$wiki$ret</li>\n";

		return $ret;
	}
}
