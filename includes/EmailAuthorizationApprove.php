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

use Html;
use HTMLForm;
use MWException;

class EmailAuthorizationApprove extends EmailAuthorizationSpecialPage {
	public function __construct( EmailAuthorizationStore $emailAuthorizationStore ) {
		parent::__construct( 'EmailAuthorizationApprove', 'emailauthorizationconfig', $emailAuthorizationStore );
	}

	/**
	 * @throws MWException
	 */
	public function executeBody() {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$output->addModuleStyles( 'ext.EmailAuthorization' );

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
		$limit = 10;
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
						] );
				$output->addHtml( $html );
				$this->createApproveButton( $url, $email );
				$this->createRejectButton( $url, $email );
				$html =
					Html::closeElement( 'td' )
					. Html::closeElement( 'tr' );
				$index++;
			} else {
				$more = true;
			}
		}

		$html .= Html::closeElement( 'table' );
		$output->addHtml( $html );

		if ( $offset > 0 || $more ) {
			$this->addTableNavigation( $offset, $more, $limit );
		}
	}

	/**
	 * @param string $url
	 * @param string $email
	 * @throws MWException
	 */
	private function createApproveButton( string $url, string $email ) {
		$this->createButton(
			$url,
			'approve-email',
			$email,
			'emailauthorization-approve-button-approve',
			[ 'progressive' ]
		);
	}

	/**
	 * @param string $url
	 * @param string $email
	 * @throws MWException
	 */
	private function createRejectButton( string $url, string $email ) {
		$this->createButton(
			$url,
			'reject-email',
			$email,
			'emailauthorization-approve-button-reject',
			[ 'destructive' ]
		);
	}

	/**
	 * @param string $url
	 * @param string $hiddenFieldName
	 * @param mixed $hiddenFieldValue
	 * @param string $buttonMessage
	 * @param array $flags
	 * @throws MWException
	 */
	private function createButton(
		string $url,
		string $hiddenFieldName,
		$hiddenFieldValue,
		string $buttonMessage,
		array $flags
	) {
		$htmlForm = HTMLForm::factory( 'ooui', [], $this->getContext() );
		$htmlForm
			->addHiddenField( $hiddenFieldName, $hiddenFieldValue )
			->addButton( [
				'name' => $hiddenFieldName . '-button',
				'value' => wfMessage( $buttonMessage ),
				'flags' => $flags
			] )
			->setAction( $url )
			->suppressDefaultSubmit()
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * @param int $offset
	 * @param bool $more
	 * @param int $limit
	 * @throws MWException
	 */
	private function addTableNavigation( int $offset, bool $more, int $limit ) {
		$output = $this->getOutput();
		$url = $this->getFullTitle()->getFullURL();
		$output->addHtml(
			Html::openElement(
				'table',
				[
					'class' => 'emailauth-navigationtable'
				]
			)
			. Html::openElement( 'tr' )
			. Html::openElement( 'td' )
		);

		if ( $offset > 0 ) {
			$this->createButton(
				$url,
				'offset',
				$offset - $limit,
				'emailauthorization-approve-button-previous',
				[]
			);
		}

		$output->addHtml(
			Html::closeElement( 'td' )
			. Html::openElement(
				'td',
				[
					'style' => 'text-align:right;'
				]
			)
		);

		if ( $more ) {
			$this->createButton(
				$url,
				'offset',
				$offset + $limit,
				'emailauthorization-approve-button-next',
				[]
			);
		}

		$output->addHtml(
			Html::closeElement( 'td' )
			. Html::closeElement( 'tr' )
			. Html::closeElement( 'table' )
		);
	}
}
