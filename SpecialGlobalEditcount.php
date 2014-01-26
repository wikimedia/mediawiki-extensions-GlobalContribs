<?php


class SpecialGlobalEditcount extends Editcount {

	public function __construct() {
		IncludableSpecialPage::__construct( 'GlobalEditcount' );
	}

	/**
	 * Count the number of edits of a user by namespace
	 *
	 * @param int $uid The user ID to check
	 * @return array
	 */
	function editsByNs( $uid ) {
		global $wgGlobalContribsWikis;

		$nscount = array();

		foreach( $wgGlobalContribsWikis as $wiki => $url ){
			$dbr = wfGetDB( DB_SLAVE, array(), $wiki );
			$res = $dbr -> select(
				array( 'revision', 'page' ),
				array( 'page_namespace', 'COUNT(*) as count' ),
				array(
					'rev_user' => $uid,
					'rev_page = page_id'
				),
				__METHOD__,
				array( 'GROUP BY' => 'page_namespace' )
			);
			foreach( $res as $row ){
				if( isset( $nscount[ $row->page_namespace ] ) ){
					$nscount[ $row->page_namespace] += intval( $row->count );
				} else {
					$nscount[ $row->page_namespace] = intval( $row->count );
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
		global $wgGlobalContribsWikis;

		$i = 0;

		foreach( $wgGlobalContribsWikis as $wiki => $url ){
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