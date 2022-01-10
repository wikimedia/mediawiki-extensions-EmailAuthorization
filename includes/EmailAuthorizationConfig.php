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
use Html;
use HTMLForm;
use MWException;
use PermissionsError;
use SpecialPage;

class EmailAuthorizationConfig extends SpecialPage {

	/**
	 * @var EmailAuthorizationStore
	 */
	private $emailAuthorizationStore;

	public function __construct( EmailAuthorizationStore $emailAuthorizationStore ) {
		parent::__construct( 'EmailAuthorizationConfig', 'emailauthorizationconfig' );
		$this->emailAuthorizationStore = $emailAuthorizationStore;
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

		$request = $this->getRequest();
		$output = $this->getOutput();
		$output->addModules( 'ext.EmailAuthorizationConfig' );
		$output->addModuleStyles( 'ext.EmailAuthorization' );

		$addEmail = trim( $request->getText( 'addemail' ) );
		$revokeEmail = trim( $request->getText( 'revokeemail' ) );

		$this->addEmail( $addEmail );
		$this->revokeEmail( $revokeEmail );

		$url = $this->getFullTitle()->getFullURL();
		if ( $request->getBool( 'showAll' ) ) {
			$this->showAllUsers();
			$this->showAuthorizedUsersButton( $url );
		} else {
			$this->showAuthorizedUsers();
			$this->showAllUsersButton( $url );
		}

		$output->addHtml( Html::element( 'hr' ) );
		$html = Html::openElement( 'p' )
			. Html::openElement( 'b' )
			. wfMessage( 'emailauthorization-config-instructions' )->parse()
			. Html::closeElement( 'b' )
			. Html::closeElement( 'p' );
		$output->addHtml( $html );

		$this->showAddForm( $url, $revokeEmail );
		$this->showRevokeForm( $url, $addEmail );
	}

	private function addEmail( $email ) {
		if ( $email === null || strlen( $email ) < 1 ) {
			return;
		}
		$validatedemail = $this->validateEmail( $email );
		if ( $validatedemail !== false ) {
			if ( $this->emailAuthorizationStore->insertEmail( $validatedemail ) ) {
				$this->displayMessage( wfMessage( 'emailauthorization-config-added', $validatedemail ) );
				$this->getHookContainer()->run( 'EmailAuthorizationAdd', [ $validatedemail ] );
			} else {
				$this->displayMessage( wfMessage( 'emailauthorization-config-alreadyauthorized', $validatedemail ) );
			}
		} else {
			$this->displayMessage( wfMessage( 'emailauthorization-config-invalidemail', $email ) );
		}
	}

	private function revokeEmail( $email ) {
		if ( $email === null || strlen( $email ) < 1 ) {
			return;
		}
		$validatedemail = $this->validateEmail( $email );
		if ( $validatedemail !== false ) {
			if ( $this->emailAuthorizationStore->deleteEmail( $validatedemail ) ) {
				$this->displayMessage( wfMessage( 'emailauthorization-config-revoked', $validatedemail ) );
				$this->getHookContainer()->run( 'EmailAuthorizationRevoke', [ $validatedemail ] );
			} else {
				$this->displayMessage( wfMessage( 'emailauthorization-config-notauthorized', $validatedemail ) );
			}
		} else {
			$this->displayMessage( wfMessage( 'emailauthorization-config-invalidemail', $email ) );
		}
	}

	private function validateEmail( $email ) {
		$email = mb_strtolower( htmlspecialchars( trim( $email ), ENT_QUOTES ) );
		if ( $email[0] === '@' ) {
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

	private function displayMessage( $message ) {
		$html = Html::openElement( 'p', [
				'class' => 'emailauth-message'
			] )
			. $message
			. Html::closeElement( 'p' );
		$this->getOutput()->addHtml( $html );
	}

	private function showAllUsers() {
		$output = $this->getOutput();
		$output->addJsConfigVars( 'EmailAuthorizationUserData', true );

		$userTable = Html::element( 'table', [
			"id" => "user-table",
			"class" => "stripe hover cell-border",
			"style" => "width: 100%"
		] );
		$output->addHtml( $userTable );
	}

	private function showAuthorizedUsers() {
		$output = $this->getOutput();
		$output->addJsConfigVars( 'EmailAuthorizationAuthorizedData', true );

		$authorizedTable = Html::element( 'table', [
			"id" => "authorized-table",
			"class" => "stripe hover cell-border",
			"style" => "width: 100%"
		] );
		$output->addHtml( $authorizedTable );
	}

	/**
	 * @param string $url
	 * @throws MWException
	 */
	private function showAllUsersButton( string $url ) {
		$htmlForm = HTMLForm::factory( 'ooui', [], $this->getContext() );
		$htmlForm
			->addHiddenField( 'showAll', true )
			->addButton( [
				'name' => 'showAllUsersForm',
				'value' => wfMessage( 'emailauthorization-config-button-showall' )->text(),
				'flags' => [ 'progressive' ]
			] )
			->setAction( $url )
			->suppressDefaultSubmit()
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * @param string $url
	 * @throws MWException
	 */
	private function showAuthorizedUsersButton( string $url ) {
		$htmlForm = HTMLForm::factory( 'ooui', [], $this->getContext() );
		$htmlForm
			->addHiddenField( 'showAll', false )
			->addButton( [
				'name' => 'showAuthorizedUsersForm',
				'value' => wfMessage( 'emailauthorization-config-button-showauth' )->text(),
				'flags' => [ 'progressive' ]
			] )
			->setAction( $url )
			->suppressDefaultSubmit()
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * @param string $url
	 * @param string $default
	 * @throws MWException
	 */
	private function showAddForm( string $url, string $default ) {
		$formDescriptor = [
			'textbox' => [
				'type' => 'text',
				'name' => 'addemail',
				'label-message' => 'emailauthorization-config-label-email',
				'size' => 50,
				'default' => $default,
				'nodata' => true,
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->addButton( [
				'name' => 'showAddForm',
				'value' => wfMessage( 'emailauthorization-config-button-add' )->text(),
				'flags' => [ 'progressive' ]
			] )
			->setAction( $url )
			->setWrapperLegendMsg( 'emailauthorization-config-legend-add' )
			->suppressDefaultSubmit()
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * @param string $url
	 * @param string $default
	 * @throws MWException
	 */
	private function showRevokeForm( string $url, string $default ) {
		$formDescriptor = [
			'textbox' => [
				'type' => 'text',
				'name' => 'revokeemail',
				'label-message' => 'emailauthorization-config-label-email',
				'size' => 50,
				'default' => $default,
				'nodata' => true,
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->addButton( [
				'name' => 'showRevokeForm',
				'value' => wfMessage( 'emailauthorization-config-button-revoke' )->text(),
				'flags' => [ 'destructive' ],
			] )
			->setAction( $url )
			->setWrapperLegendMsg( 'emailauthorization-config-legend-revoke' )
			->suppressDefaultSubmit()
			->prepareForm()
			->displayForm( false );
	}
}
