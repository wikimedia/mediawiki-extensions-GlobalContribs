<?php

class GlobalEditcountHTML extends EditcountHTML {

	/**
	 * Not ideal, but for calls to $this->getTitle() return Editcount (no global) otherwise
	 * @param string|false $subpage
	 * @return Title
	 */
	public function getPageTitle( $subpage = false ) {
		return SpecialPage::getTitleFor( 'GlobalEditcount' );
	}

}
