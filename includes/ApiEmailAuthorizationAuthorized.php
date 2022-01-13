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

use ApiMain;
use ParserFactory;
use Wikimedia\ParamValidator\ParamValidator;

class ApiEmailAuthorizationAuthorized extends ApiEmailAuthorizationBase {

	/**
	 * @param ApiMain $main
	 * @param string $action
	 * @param EmailAuthorizationStore $emailAuthorizationStore
	 * @param ParserFactory $parserFactory
	 */
	public function __construct(
		ApiMain $main,
		string $action,
		EmailAuthorizationStore $emailAuthorizationStore,
		ParserFactory $parserFactory
	) {
		parent::__construct( $main, $action, $emailAuthorizationStore, $parserFactory );
	}

	/**
	 * @return array
	 */
	public function getAllowedParams(): array {
		$allowedParams = parent::getAllowedParams();
		$allowedParams["columns"] = [
			ParamValidator::PARAM_ISMULTI => true,
			ParamValidator::PARAM_DEFAULT => "email|userNames|realNames|userPages"
		];
		return $allowedParams;
	}

	/**
	 * @param array $params
	 * @return array
	 */
	public function executeBody( array $params ): array {
		$authorized = $this->emailAuthorizationStore->getAuthorizedEmails(
			intval( $params["offset"] ),
			intval( $params["limit"] ),
			$params["search"],
			$params["columns"],
			$params["order"]
		);
		$authorizedData = [];
		foreach ( $authorized as $address ) {
			$email = htmlspecialchars( $address->email, ENT_QUOTES );
			if ( strlen( $email ) > 1 && $email[0] === '@' ) {
				$authorizedData[] = [
					"email" => wfMessage( 'emailauthorization-config-value-domain', $email )->text(),
					"userNames" => [],
					"realNames" => [],
					"userPages" => []
				];
			} else {
				$users = $this->emailAuthorizationStore->getUserInfo( $email );
				if ( !$users->valid() ) {
					$authorizedData[] = [
						"email" => $email,
						"userNames" => [],
						"realNames" => [],
						"userPages" => []
					];
				} else {
					$userNames = [];
					$realNames = [];
					$userPages = [];
					foreach ( $users as $user ) {
						$user_name = htmlspecialchars( $user->user_name, ENT_QUOTES );
						$userNames[] = $user_name;
						$realNames[] = htmlspecialchars( $user->user_real_name, ENT_QUOTES );
						$userPages[] = $this->parse( "[[User:$user_name]]" );
					}
					$authorizedData[] = [
						"email" => $email,
						"userNames" => $userNames,
						"realNames" => $realNames,
						"userPages" => implode( '<br/>', $userPages )
					];
				}
			}
		}
		$filteredAuthorizedCount = count( $authorizedData );
		if ( is_string( $params["search"] ) && strlen( $params["search"] ) > 0 ) {
			$authorizedCount = $this->emailAuthorizationStore->getAuthorizedEmailsCount();
		} else {
			$authorizedCount = $filteredAuthorizedCount;
		}
		return [
			"draw" => intval( $params["draw"] ),
			"recordsTotal" => $authorizedCount,
			"recordsFiltered" => $filteredAuthorizedCount,
			"data" => $authorizedData
		];
	}

	/**
	 * @return string[]
	 */
	public function getExamplesMessages(): array {
		return [
			"action={$this->getModuleName()}&draw=1" =>
			"apihelp-{$this->getModuleName()}-standard-example"
		];
	}
}
