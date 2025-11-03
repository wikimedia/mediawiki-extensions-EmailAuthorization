<?php
/*
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

namespace MediaWiki\Extension\EmailAuthorization;

use ErrorPageError;
use MediaWiki\Html\Html;
use MWException;
use PermissionsError;
use SpecialPage;

abstract class EmailAuthorizationSpecialPage extends SpecialPage {

	/**
	 * @var EmailAuthorizationStore
	 */
	protected $emailAuthorizationStore;

	/**
	 * @param string $name
	 * @param string $restriction
	 * @param EmailAuthorizationStore $emailAuthorizationStore
	 */
	public function __construct(
		string $name,
		string $restriction,
		EmailAuthorizationStore $emailAuthorizationStore
	) {
		parent::__construct( $name, $restriction );
		$this->emailAuthorizationStore = $emailAuthorizationStore;
	}

	/**
	 * @return string
	 */
	public function getGroupName(): string {
		return 'login';
	}

	/**
	 * @param string|null $subPage
	 * @throws PermissionsError|ErrorPageError|MWException
	 */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->checkPermissions();
		$securityLevel = $this->getLoginSecurityLevel();
		if ( $securityLevel !== false && !$this->checkLoginSecurityLevel( $securityLevel ) ) {
			return;
		}
		$this->outputHeader();

		$this->executeBody();
	}

	abstract protected function executeBody();

	/**
	 * @param string $email
	 * @param bool $allowDomain
	 * @return false|string
	 */
	protected function validateEmail( string $email, bool $allowDomain = true ) {
		if ( strlen( $email ) < 1 ) {
			return false;
		}
		$email = mb_strtolower( htmlspecialchars( trim( $email ), ENT_QUOTES ) );
		if ( $email[0] === '@' && $allowDomain ) {
			if ( filter_var( 'a' . $email, FILTER_VALIDATE_EMAIL ) ) {
				return $email;
			}
		} else {
			if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				return $email;
			}
		}
		return false;
	}

	/**
	 * @param string $message
	 */
	protected function displayMessage( string $message ) {
		$html = Html::openElement( 'p', [
				'class' => 'emailauth-message'
			] )
			. $message
			. Html::closeElement( 'p' );
		$this->getOutput()->addHtml( $html );
	}
}
