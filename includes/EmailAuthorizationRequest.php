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
use HTMLForm;
use MediaWiki\Config\ServiceOptions;
use MWException;
use WebRequest;

class EmailAuthorizationRequest extends EmailAuthorizationSpecialPage {
	public const CONSTRUCTOR_OPTIONS = [
		'EmailAuthorization_RequestFields'
	];

	/**
	 * @var array
	 */
	private $requestFields;

	/**
	 * @param EmailAuthorizationStore $emailAuthorizationStore
	 * @param Config $config
	 */
	public function __construct( EmailAuthorizationStore $emailAuthorizationStore, Config $config ) {
		parent::__construct( 'EmailAuthorizationRequest', '', $emailAuthorizationStore );
		$options = new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $config );
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->requestFields = $options->get( 'EmailAuthorization_RequestFields' );
	}

	/**
	 * @throws MWException
	 */
	public function executeBody() {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$output->addModuleStyles( 'ext.EmailAuthorization' );

		$emailLabel = wfMessage( 'emailauthorization-request-label-email' )->text();
		$emailfield = [
			'label' => $emailLabel,
			'mandatory' => true
		];
		$fields = array_merge( [ $emailfield ], $this->requestFields );
		$submitted = $request->getBool( 'wpemailauthorization-request-field-submitted' );
		if ( $submitted ) {
			$showform = self::processRequest( $request, $fields );
		} else {
			$showform = true;
		}

		if ( $showform ) {
			$url = $this->getFullTitle()->getFullURL();
			$id = 'emailauthorization-request-field-submitted';
			$i = 0;
			$formDescriptor = [
				$id => [
					'id' => $id,
					'type' => 'hidden',
					'default' => true
				]
			];
			foreach ( $fields as $field ) {
				if ( isset( $field[ 'label' ] ) ) {
					$id = 'emailauthorization-request-field-' . $i;
					$i++;
					$element = [];
					$element[ 'id' ] = $id;
					$element[ 'label' ] = $field[ 'label' ];
					if ( isset( $field[ 'mandatory' ] ) && $field[ 'mandatory' ] ) {
						$element[ 'required' ] = true;
					}
					if ( isset( $field[ 'values' ] ) ) {
						$element[ 'type' ] = 'select';
						$options = [];
						foreach ( $field[ 'values' ] as $value ) {
							$options[ $value ] = $value;
						}
						$element[ 'options' ] = $options;
					} elseif ( isset( $field[ 'rows' ] ) ) {
						$element[ 'type' ] = 'textarea';
						$element[ 'rows' ] = $field[ 'rows' ];
						$element[ 'size' ] = 50;
						if ( isset( $field[ 'columns' ] ) ) {
							$element[ 'size' ] = $field[ 'columns' ];
						}
						if ( $submitted ) {
							$element[ 'default' ] = $request->getText( $id );
						}
					} else {
						$element[ 'type' ] = 'text';
						$element[ 'size' ] = 50;
						if ( isset( $field[ 'columns' ] ) ) {
							$element[ 'size' ] = $field[ 'columns' ];
						}
						if ( $submitted ) {
							$element[ 'default' ] = $request->getText( $id );
						}
					}
					$formDescriptor[ $id ] = $element;
				}
			}

			$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
			$htmlForm
				->addButton( [
					'name' => 'RequestEmail',
					'value' => wfMessage( 'emailauthorization-request-button-submit' ),
					'flags' => [ 'progressive' ]
				] )
				->setAction( $url )
				->setWrapperLegendMsg( 'emailauthorization-request-instructions' )
				->suppressDefaultSubmit()
				->prepareForm()
				->displayForm( false );
		}
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
			$id = 'wpemailauthorization-request-field-' . $i;
			$i++;
			if ( isset( $field[ 'label' ] ) ) {
				if ( isset( $field[ 'mandatory' ] ) && $field[ 'mandatory' ] &&
					$request->getText( $id ) === '' ) {
					$this->displayMessage( wfMessage( 'emailauthorization-request-missingmandatory' ) );
					return true;
				}
			}
		}
		$email = $request->getText( 'wpemailauthorization-request-field-0' );
		$validatedemail = $this->validateEmail( $email, false );
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

	/**
	 * @param string $email
	 * @return bool
	 */
	private function isEmailRequestable( string $email ): bool {
		$users = $this->emailAuthorizationStore->getUserInfo( $email );
		if ( $users->valid() ) {
			return !$this->emailAuthorizationStore->isEmailAuthorized( $email );
		}
		return true;
	}

	/**
	 * @param string $email
	 * @param WebRequest $request
	 * @return bool
	 */
	private function insertRequest( string $email, WebRequest $request ): bool {
		$i = 1;
		$data = [];
		foreach ( $this->requestFields as $field ) {
			$id = 'wpemailauthorization-request-field-' . $i;
			$i++;
			$value = $request->getText( $id );
			if ( $value ) {
				$data[ $field[ 'label' ] ] = $value;
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
