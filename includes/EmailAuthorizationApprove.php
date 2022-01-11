<?php
/*
 * Permission is hereby granted, free of charge, to any person obtaining a
 t copy of this software and associated documentation files (the "Software"),
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
use Html;
use PermissionsError;
use SpecialPage;
use Xml;

class EmailAuthorizationApprove extends SpecialPage {

	/**
	 * @var EmailAuthorizationStore
	 */
	private $emailAuthorizationStore;

	public function __construct( EmailAuthorizationStore $emailAuthorizationStore ) {
		parent::__construct( 'EmailAuthorizationApprove', 'emailauthorizationconfig' );
		$this->emailAuthorizationStore = $emailAuthorizationStore;
	}

	public function getGroupName() {
		return 'login';
	}

	/**
	 * @param string|null $subPage
	 * @throws PermissionsError
	 * @throws ErrorPageError
	 */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->checkPermissions();
		$securityLevel = $this->getLoginSecurityLevel();
		if ( $securityLevel !== false && !$this->checkLoginSecurityLevel( $securityLevel ) ) {
			return;
		}
		$this->outputHeader();

		$request = $this->getRequest();
		$this->getOutput()->addModuleStyles( 'ext.EmailAuthorization' );

		$approve_email = $request->getText( 'approve-email' );
		if ( $approve_email !== null && strlen( $approve_email ) ) {
			$fields = $this->emailAuthorizationStore->getRequestFields( $approve_email );
			$this->emailAuthorizationStore->insertEmail( $approve_email );
			$this->emailAuthorizationStore->deleteRequest( $approve_email );
			$this->displayMessage( wfMessage( 'emailauthorization-approve-approved', $approve_email ) );
			$this->getHookContainer()->run(
				'EmailAuthorizationApprove',
				[ $approve_email, $fields, $this->getUser() ]
			);
		}

		$reject_email = $request->getText( 'reject-email' );
		if ( $reject_email !== null && strlen( $reject_email ) ) {
			$fields = $this->emailAuthorizationStore->getRequestFields( $reject_email );
			$this->emailAuthorizationStore->deleteRequest( $reject_email );
			$this->displayMessage( wfMessage( 'emailauthorization-approve-rejected', $reject_email ) );
			$this->getHookContainer()->run( 'EmailAuthorizationReject', [ $reject_email, $fields, $this->getUser() ] );
		}

		$offset = $request->getText( 'offset' );

		if ( !is_numeric( $offset ) || strlen( $offset ) === 0 || $offset < 0 ) {
			$offset = 0;
		}

		$limit = 20;

		$requests = $this->emailAuthorizationStore->getRequests( $limit + 1, $offset );

		if ( !$requests->valid() ) {
			$offset = 0;
			$requests = $this->emailAuthorizationStore->getRequests( $limit + 1, $offset );
			if ( !$requests->valid() ) {
				$this->displayMessage( wfMessage( 'emailauthorization-approve-norequestsfound' ) );
				return;
			}
		}

		$html = Html::openElement( 'table', [
				'class' => 'wikitable emailauth-wikitable'
			] )
			. Html::openElement( 'tr' )
			. Html::openElement( 'th' )
			. wfMessage( 'emailauthorization-approve-label-email' )
			. Html::closeElement( 'th' )
			. Html::openElement( 'th' )
			. wfMessage( 'emailauthorization-approve-label-extra' )
			. Html::closeElement( 'th' )
			. Html::openElement( 'th' )
			. wfMessage( 'emailauthorization-approve-label-action' )
			. Html::closeElement( 'th' )
			. Html::closeElement( 'tr' );

		$index = 0;
		$more = false;
		$url = $this->getFullTitle()->getFullURL();
		foreach ( $requests as $request ) {
			if ( $index < $limit ) {
				$email = htmlspecialchars( $request->email, ENT_QUOTES );
				$html .= Html::openElement( 'tr' )
					. Html::openElement( 'td' )
					. $email
					. Html::closeElement( 'td' )
					. Html::openElement( 'td' );
				$json = $request->request;
				$data = json_decode( $json );
				foreach ( $data as $field => $value ) {
					$html .=
						Html::openElement( 'b' ) .
						htmlspecialchars( $field ) .
						Html::closeElement( 'b' ) .
						 ': ' .
						htmlspecialchars( $value ) .
						Html::element( 'br' );
				}
				$html .=
					Html::closeElement( 'td' )
					. Html::openElement( 'td', [
							'style' => 'text-align: center;'
						] )
					. $this->createApproveButton( $url, $email )
					. $this->createRejectButton( $url, $email )
					. Html::closeElement( 'td' )
					. Html::closeElement( 'tr' );
				$index++;
			} else {
				$more = true;
			}
		}

		$html .= Html::closeElement( 'table' );
		$this->getOutput()->addHtml( $html );

		if ( $offset > 0 || $more ) {
			$this->addTableNavigation( $offset, $more, $limit, 'offset' );
		}
	}

	private function createApproveButton( $url, $email ): string {
		return Html::openElement( 'form', [
				'method' => 'post',
				'action' => $url,
				'style' => 'display: inline-block;'
			] )
			. Html::hidden( 'approve-email', $email )
			. Xml::submitButton(
				wfMessage( 'emailauthorization-approve-button-approve' ),
				[ 'class' => 'emailauth-button' ] )
			. Html::closeElement( 'form' );
	}

	private function createRejectButton( $url, $email ): string {
		return Html::openElement( 'form', [
				'method' => 'post',
				'action' => $url,
				'style' => 'display: inline-block;'
			] )
			. Html::hidden( 'reject-email', $email )
			. Xml::submitButton(
				wfMessage( 'emailauthorization-approve-button-reject' ),
				[ 'class' => 'emailauth-button' ] )
			. Html::closeElement( 'form' );
	}

	private function addTableNavigation( $offset, $more, $limit, $paramname ) {
		$url = $this->getFullTitle()->getFullURL();

		$html = Html::openElement( 'table', [
				'class' => 'emailauth-navigationtable'
			] )
			. Html::openElement( 'tr' )
			. Html::openElement( 'td' );

		if ( $offset > 0 ) {
			$prevurl = $url . '?' . $paramname . '=' . ( $offset - $limit );
			$html .= Html::openElement( 'a', [
					'href' => $prevurl,
					'class' => 'emailauth-button'
				] )
				. wfMessage( 'emailauthorization-approve-button-previous' )
				. Html::closeElement( 'a' );
		}

		$html .= Html::closeElement( 'td' )
			. Html::openElement( 'td', [
				'style' => 'text-align:right;'
			] );

		if ( $more ) {
			$nexturl = $url . '?' . $paramname . '=' . ( $offset + $limit );
			$html .= Html::openElement( 'a', [
					'href' => $nexturl,
					'class' => 'emailauth-button'
				] )
				. wfMessage( 'emailauthorization-approve-button-next' )
				. Html::closeElement( 'a' );
		}

		$html .= Html::closeElement( 'td' )
			. Html::closeElement( 'tr' )
			. Html::closeElement( 'table' );
		$this->getOutput()->addHtml( $html );
	}

	private function displayMessage( $message ) {
		$html = Html::openElement( 'p', [
				'class' => 'emailauth-message'
			] )
			. $message
			. Html::closeElement( 'p' );
		$this->getOutput()->addHtml( $html );
	}
}
