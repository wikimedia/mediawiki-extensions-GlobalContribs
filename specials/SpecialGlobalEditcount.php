<?php

use MediaWiki\MediaWikiServices;

class SpecialGlobalEditcount extends Editcount {

	public function __construct() {
		IncludableSpecialPage::__construct( 'GlobalEditcount' );
	}

	public function execute( $par ) {
		global $wgRequest, $wgOut, $wgContLang;

		$target = isset( $par ) ? $par : $wgRequest->getText( 'username' );

		$target = explode( '/', $target, 2 );
		$username = $target[0];
		$namespace = isset( $target[1] )
			? MediaWikiServices::getInstance()->getContentLanguage()->getNsIndex( $target[1] )
			: null;

		$username = Title::newFromText( $username );
		$username = is_object( $username ) ? $username->getText() : '';

		$uid = User::idFromName( $username );

		if ( $this->including() ) {
			if ( $namespace === null ) {
				if ( $uid != 0 ) {
					$out = $wgContLang->formatNum( User::newFromName( $username )->getEditCount() );
				} else {
					$out = '';
				}
			} else {
				$out = $wgContLang->formatNum( $this->editsInNs( $uid, $namespace ) );
			}
			$wgOut->addHTML( $out );
		} else {
			$nscount = $this->editsByNs( $uid );
			$html = new GlobalEditcountHTML;
			$html->outputHTML( $username, $uid, $nscount, array_sum( $nscount ) );
		}
	}

	/**
	 * Count the number of edits of a user by namespace
	 *
	 * @param int $uid The user ID to check
	 * @return array
	 */
	protected function editsByNs( $uid ) {
		global $wgConf;

		if ( $uid <= 0 ) {
			return [];
		}

		$nscount = array();

		foreach ( $wgConf->wikis as $wiki ) {
			$dbr = wfGetDB( DB_REPLICA, array(), $wiki );
			$res = $dbr->select(
				array( 'revision', 'page' ),
				array( 'page_namespace', 'COUNT(*) AS count' ),
				array(
					'rev_user' => $uid,
					'rev_page = page_id'
				),
				__METHOD__,
				array( 'GROUP BY' => 'page_namespace' )
			);
			foreach ( $res as $row ) {
				if ( isset( $nscount[$row->page_namespace] ) ) {
					$nscount[$row->page_namespace] += intval( $row->count );
				} else {
					$nscount[$row->page_namespace] = intval( $row->count );
				}
			}
		}

		return $nscount;
	}

	/**
	 * Count the number of edits of a user in a given namespace
	 *
	 * @param int $uid The user ID to check
	 * @param int $ns The namespace to check
	 * @return int
	 */
	protected function editsInNs( $uid, $ns ) {
		global $wgConf;

		if ( $uid <= 0 ) {
			return 0;
		}

		$i = 0;

		foreach ( $wgConf->wikis as $wiki ) {
			$dbr = wfGetDB( DB_REPLICA, array(), $wiki );
			$res = $dbr->selectField(
				array( 'revision', 'page' ),
				array( 'COUNT(*) AS count' ),
				array(
					'page_namespace' => $ns,
					'rev_user' => $uid,
					'rev_page = page_id'
				),
				__METHOD__,
				array( 'GROUP BY' => 'page_namespace' )
			);
			$i += intval( $res );
		}

		return $i;
	}
}

class GlobalEditcountHTML extends EditcountHTML {

	/**
	 * Not ideal, but for calls to $this->getTitle() return Editcount (no global) otherwise
	 */
	public function getPageTitle( $subpage = false ) {
		return SpecialPage::getTitleFor( 'GlobalEditcount' );
	}
}

