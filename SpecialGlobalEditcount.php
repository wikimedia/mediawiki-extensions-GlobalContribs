<?php

class SpecialGlobalEditcount extends Editcount {

	public function __construct() {
		IncludableSpecialPage::__construct( 'GlobalEditcount' );
	}

	/**
	 * main()
	 */
	public function execute( $par ) {
		global $wgRequest, $wgOut, $wgContLang;

		$target = isset( $par ) ? $par : $wgRequest->getText( 'username' );

		list( $username, $namespace ) = $this->extractParamaters( $target );

		$username = Title::newFromText( $username );
		$username = is_object( $username ) ? $username->getText() : '';

		$uid = User::idFromName( $username );

		if ( $this->including() ) {
			if ( $namespace === null ) {
				if ($uid != 0)
					$out = $wgContLang->formatNum( User::edits( $uid ) );
				else
					$out = "";
			} else {
				$out = $wgContLang->formatNum( $this->editsInNs( $uid, $namespace ) );
			}
			$wgOut->addHTML( $out );
		} else {
			if ($uid != 0)
				$total = $this->getTotal( $nscount = $this->editsByNs( $uid ) );
			$html = new GlobalEditcountHTML;
			$html->outputHTML( $username, $uid, @$nscount, @$total );
		}
	}

	/**
	 * Count the number of edits of a user by namespace
	 *
	 * @param int $uid The user ID to check
	 * @return array
	 */
	function editsByNs( $uid ) {
		global $wgConf;

		$nscount = array();

		foreach ( $wgConf->wikis as $wiki ) {
			$dbr = wfGetDB( DB_SLAVE, array(), $wiki );
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
	 * @param int $ns  The namespace to check
	 * @return string
	 */
	function editsInNs( $uid, $ns ) {
		global $wgConf;

		$i = 0;

		foreach ( $wgConf->wikis as $wiki ) {
			$dbr = wfGetDB( DB_SLAVE, array(), $wiki );
			$res = $dbr->selectField(
				array( 'revision', 'page' ),
				array( 'COUNT(*) as count' ),
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

		return strval( $i );
	}
}

class GlobalEditcountHTML extends EditcountHTML {

	/**
	 * Not ideal, but for calls to $this->getTitle() return Editcount (no global) otherwise
	 */
	function getPageTitle() {
		return SpecialPage::getTitleFor( 'GlobalEditcount' );
	}
}

