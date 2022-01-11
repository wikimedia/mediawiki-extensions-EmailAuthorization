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

use Config;
use EchoEvent;
use ExtensionRegistry;
use Html;
use MediaWiki\Config\ServiceOptions;
use MWException;
use SpecialPage;
use WebRequest;
use Xml;

class EmailAuthorizationRequest extends SpecialPage {
	public const CONSTRUCTOR_OPTIONS = [
		'EmailAuthorization_RequestFields'
	];

	/**
	 * @var EmailAuthorizationStore
	 */
	private $emailAuthorizationStore;

	/**
	 * @var array
	 */
	private $requestFields;

	public function __construct( EmailAuthorizationStore $emailAuthorizationStore, Config $config ) {
		parent::__construct( 'EmailAuthorizationRequest' );
		$this->emailAuthorizationStore = $emailAuthorizationStore;
		$options = new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $config );
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->requestFields = $options->get( 'EmailAuthorization_RequestFields' );
	}

	public function getGroupName() {
		return 'login';
	}

	/**
	 * @param string|null $subPage
	 * @throws MWException
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

		$emailLabel = wfMessage( 'emailauthorization-request-label-email' )->text();
		$emailfield = [
			'label' => $emailLabel,
			'mandatory' => true
		];
		$fields = array_merge( [ $emailfield ], $this->requestFields );

		$showform = true;

		$submitted = $request->getBool( 'emailauthorization-request-field-submitted' );

		if ( $submitted ) {
			$showform = self::processRequest( $request, $fields );
		}

		if ( $showform ) {
			$html = Html::openElement( 'p' )
				. Html::openElement( 'b' )
				. wfMessage( 'emailauthorization-request-instructions' )->parse()
				. Html::closeElement( 'b' )
				. Html::closeElement( 'p' )
				. Html::element( 'br' );
			$this->getOutput()->addHtml( $html );

			$url = $this->getFullTitle()->getFullURL();
			$html = Html::openElement( 'form', [
					'method' => 'post',
					'action' => $url,
					'id' => 'RequestEmail'
				] );
			$id = 'emailauthorization-request-field-submitted';
			$html .= Html::hidden( $id, true );
			$i = 0;
			foreach ( $fields as $field ) {
				$id = 'emailauthorization-request-field-' . $i;
				$i++;
				if ( isset( $field['label'] ) ) {
					$html .= Xml::label( $field['label'] . ': ', $id );
					$mandatory = false;
					if ( isset( $field['mandatory'] ) && $field['mandatory'] ) {
						$html .= '* ';
						$mandatory = true;
					}
					$attribs = [ 'id' => $id ];
					if ( $submitted && $mandatory && $request->getText( $id ) === '' ) {
						$attribs['style'] = "border-color:red;";
					}
					if ( isset( $field['values'] ) ) {
						$attribs['name'] = $id;
						$html .= Xml::openElement( 'select', $attribs );
						foreach ( $field['values'] as $value ) {
							$html .= Xml::option( $value, $value );
						}
						$html .= Xml::closeElement( 'select' );
						$html .= Html::element( 'br' );
					} elseif ( isset( $field['rows'] ) ) {
						$rows = $field['rows'];
						$columns = 50;
						if ( isset( $field['columns'] ) ) {
							$columns = $field['columns'];
						}
						$value = '';
						if ( $submitted ) {
							$value = $request->getText( $id );
						}
						$input = Xml::textarea( $id, $value, $columns, $rows, $attribs );
						$html .= $input;
					} else {
						$columns = 50;
						if ( isset( $field['columns'] ) ) {
							$columns = $field['columns'];
						}
						$value = '';
						if ( $submitted ) {
							$value = $request->getText( $id );
						}
						$input = Xml::input( $id, $columns, $value, $attribs );
						$html .= $input;
						$html .= Html::element( 'br' );
					}
					$html .= Html::element( 'br' );
				}
			}
			$html .= Xml::submitButton(
				wfMessage( 'emailauthorization-request-button-submit' ),
				[ 'class' => 'emailauth-button' ] )
				. Html::closeElement( 'form' );
			$this->getOutput()->addHtml( $html );
		}
	}

	private function displayMessage( $message ) {
		$html = Html::openElement( 'p', [
				'class' => 'emailauth-message'
			] )
			. $message
			. Html::closeElement( 'p' );
		$this->getOutput()->addHtml( $html );
	}

	private function validateEmail( $email ) {
		if ( $email === null || strlen( $email ) < 1 ) {
			return false;
		}
		$email = mb_strtolower( htmlspecialchars( trim( $email ), ENT_QUOTES ) );
		if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return $email;
		}
		return false;
	}

	/**
	 * @param WebRequest $request
	 * @param array $fields
	 * @return bool
	 * @throws MWException
	 */
	private function processRequest( WebRequest $request, array $fields ): bool {
		$i = 0;
		foreach ( $fields as $field ) {
			$id = 'emailauthorization-request-field-' . $i;
			$i++;
			if ( isset( $field['label'] ) ) {
				if ( isset( $field['mandatory'] ) && $field['mandatory'] &&
					$request->getText( $id ) === '' ) {
					$this->displayMessage(
						wfMessage( 'emailauthorization-request-missingmandatory' ) );
					return true;
				}
			}
		}
		$email = $request->getText( 'emailauthorization-request-field-0' );
		$validatedemail = $this->validateEmail( $email );
		if ( $validatedemail === false ) {
			$this->displayMessage( wfMessage( 'emailauthorization-request-invalidemail', $email ) );
			return true;
		}
		$email = $validatedemail;
		if ( $this->isEmailRequestable( $email ) ) {
			if ( self::insertRequest( $email, $request ) ) {
				if ( ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
					$extra = [
						'email' => $validatedemail,
						'notifyAgent' => true
					];
					EchoEvent::create( [
						'type' => 'emailauthorization-account-request',
						'extra' => $extra
					] );
				}
				$this->displayMessage( wfMessage( 'emailauthorization-request-requested', $validatedemail ) );
				return false;
			} else {
				$this->displayMessage( wfMessage( 'emailauthorization-request-error', $validatedemail ) );
				return true;
			}
		}
		$this->displayMessage( wfMessage( 'emailauthorization-request-unavailable', $validatedemail ) );
		return true;
	}

	private function isEmailRequestable( $email ): bool {
		$users = $this->emailAuthorizationStore->getUserInfo( $email );
		if ( $users->valid() ) {
			return !$this->emailAuthorizationStore->isEmailAuthorized( $email );
		}
		return true;
	}

	private function insertRequest( $email, $request ): bool {
		$i = 1;
		$data = [];
		foreach ( $this->requestFields as $field ) {
			$id = 'emailauthorization-request-field-' . $i;
			$i++;
			$value = $request->getText( $id );
			if ( $value ) {
				$data[$field['label']] = $value;
			}
		}
		$json = json_encode( $data );
		$res = $this->emailAuthorizationStore->insertRequest( $email, $json );
		if ( $res ) {
			$this->getHookContainer()->run( 'EmailAuthorizationRequest', [ $email, $data ] );
			return true;
		}
		return false;
	}
}
