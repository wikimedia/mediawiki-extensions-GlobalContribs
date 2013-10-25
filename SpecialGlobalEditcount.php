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
		$fname = 'Editcount::editsByNs';
		$nscount = array();

		global $gcwikis;
		foreach( $gcwikis as $wiki ){
			$dbr = wfGetDB( DB_SLAVE, array(), $wiki );
			$res = $dbr -> select(
				array( 'user', 'revision', 'page' ),
				array( 'page_namespace', 'COUNT(*) as count' ),
				array(
					'user_id' => $uid,
					'rev_user = user_id',
					'rev_page = page_id'
				),
				$fname,
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
		
		$this -> resT = $dbr->selectSQLText(
				array( 'user', 'revision', 'page' ),
				array( 'page_namespace', 'COUNT(*) as count' ),
				array(
						'user_id' => $uid,
						'rev_user = user_id',
						'rev_page = page_id'
				),
				$fname,
				array( 'GROUP BY' => 'page_namespace' )
		);
		
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
		$fname = 'Editcount::editsInNs';
		$i = 0;
		global $gcwikis;
		foreach( $gcwikis as $wiki ){
			$dbr = wfGetDB( DB_SLAVE, array(), $wiki );
			$res = $dbr->selectField(
				array( 'user', 'revision', 'page' ),
				array( 'COUNT(*) as count' ),
				array(
					'user_id' => $uid,
					'page_namespace' => $ns,
					'rev_user = user_id',
					'rev_page = page_id'
				),
				$fname,
				array( 'GROUP BY' => 'page_namespace' )
			);
			$i += intval( $res );
		}
		return strval( $i );
	}
}