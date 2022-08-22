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
use Wikimedia\IPUtils;

/**
 * Special:GlobalContributions, show user contributions in a paged list
 *
 * @ingroup SpecialPage
 */
class SpecialGlobalContributions extends SpecialContributions {

	public function getDescription() {
		return $this->msg( 'globalcontribs' )->text();
	}

	public function __construct() {
		parent::__construct();
		$this->mName = 'GlobalContributions';
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();

		$out = $this->getOutput();
		$out->addModuleStyles( 'mediawiki.special' );

		$this->opts = [];
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
		} elseif ( $par === 'newbies' ) {
			$target = 'newbies';
			$this->opts['contribs'] = 'newbie';
		} else {
			$this->opts['contribs'] = 'user';
		}

		$this->opts['deletedOnly'] = $request->getBool( 'deletedOnly' );

		if ( !strlen( $target ) ) {
			$out->addHTML( $this->getForm( [] ) );
			return;
		}

		$user = $this->getUser();

		$rclimit = MediaWikiServices::getInstance()->getUserOptionsManager()
			->getOption( $user, 'rclimit' );
		$this->opts['limit'] = $request->getInt( 'limit', $rclimit );
		$this->opts['target'] = $target;
		$this->opts['topOnly'] = $request->getBool( 'topOnly' );

		$nt = Title::makeTitleSafe( NS_USER, $target );
		if ( !$nt ) {
			$out->addHTML( $this->getForm( [] ) );
			return;
		}
		$userObj = User::newFromName( $nt->getText(), false );
		if ( !$userObj ) {
			$out->addHTML( $this->getForm( [] ) );
			return;
		}
		$id = $userObj->getID();

		if ( $this->opts['contribs'] != 'newbie' ) {
			$target = $nt->getText();
			$out->addSubtitle( $this->contributionsSub( $userObj, $target ) );
			$out->setHTMLTitle( $this->msg( 'pagetitle', $this->msg( 'contributions-title', $target )->plain() ) );
			$this->getSkin()->setRelevantUser( $userObj );
		} else {
			$out->addSubtitle( $this->msg( 'sp-contributions-newbies-sub' ) );
			$out->setHTMLTitle( $this->msg( 'pagetitle', $this->msg( 'sp-contributions-newbies-title' )->plain() ) );
		}

		$ns = $request->getVal( 'namespace', null );
		if ( $ns !== null && $ns !== '' ) {
			$this->opts['namespace'] = intval( $ns );
		} else {
			$this->opts['namespace'] = '';
		}

		$this->opts['associated'] = $request->getBool( 'associated' );

		$this->opts['nsInvert'] = (bool)$request->getVal( 'nsInvert' );

		$this->opts['tagfilter'] = (string)$request->getVal( 'tagfilter' );

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
			$apiParams = [
				'action' => 'feedcontributions',
				'feedformat' => $feedType,
				'user' => $target,
			];
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
		$this->addFeedLinks( [ 'action' => 'feedcontributions', 'user' => $target ] );

		if ( Hooks::run( 'SpecialContributionsBeforeMainOutput', [ $id ] ) ) {
			$out->addHTML( $this->getForm( [] ) );

			$pager = new GlobalContribsPager( $this->getContext(), [
				'target' => $target,
				'contribs' => $this->opts['contribs'],
				'namespace' => $this->opts['namespace'],
				'year' => $this->opts['year'],
				'month' => $this->opts['month'],
				'deletedOnly' => $this->opts['deletedOnly'],
				'topOnly' => $this->opts['topOnly'],
				'nsInvert' => $this->opts['nsInvert'],
				'associated' => $this->opts['associated'],
			] );
			if ( !$pager->getNumRows() ) {
				$out->addWikiMsg( 'nocontribs', $target );
			} else {
				# Show a message about slave lag, if applicable
				$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
				$lag = $pager->getDatabase()->getLag();
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
			} elseif ( IPUtils::isIPAddress( $target ) ) {
				$message = 'sp-contributions-footer-anon';
			} elseif ( $userObj->isRegistered() ) {
				$message = 'sp-contributions-footer';
			} else {
				// No message for non-existing users
				$message = '';
			}

			if ( $message ) {
				if ( !$this->msg( $message, $target )->isDisabled() ) {
					$out->wrapWikiMsg(
						"<footer class='mw-contributions-footer'>\n$1\n</footer>",
						[ $message, $target ]
					);
				}
			}
		}
	}

}
