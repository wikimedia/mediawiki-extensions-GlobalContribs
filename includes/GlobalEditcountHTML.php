<?php

class GlobalEditcountHTML extends EditcountHTML {

	/**
	 * Not ideal, but for calls to $this->getTitle() return Editcount (no global) otherwise
	 */
	public function getPageTitle( $subpage = false ) {
		return SpecialPage::getTitleFor( 'GlobalEditcount' );
	}

}
