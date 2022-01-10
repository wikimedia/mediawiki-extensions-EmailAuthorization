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
use OOUI\IconWidget;
use ParserFactory;
use Wikimedia\ParamValidator\ParamValidator;

class ApiEmailAuthorizationUsers extends ApiEmailAuthorizationBase {

	/**
	 * @var EmailAuthorizationService
	 */
	private $emailAuthorizationService;

	public function __construct(
		ApiMain $main,
		string $action,
		EmailAuthorizationStore $emailAuthorizationStore,
		EmailAuthorizationService $emailAuthorizationService,
		ParserFactory $parserFactory
	) {
		parent::__construct( $main, $action, $emailAuthorizationStore, $parserFactory );
		$this->emailAuthorizationService = $emailAuthorizationService;
	}

	public function getAllowedParams(): array {
		$allowedParams = parent::getAllowedParams();
		$allowedParams["columns"] = [
			ParamValidator::PARAM_ISMULTI => true,
			ParamValidator::PARAM_DEFAULT => "email|userName|realName|userPage|authorized"
		];
		return $allowedParams;
	}

	public function executeBody( $params ): array {
		$users = $this->emailAuthorizationStore->getUsers(
			intval( $params["offset"] ),
			intval( $params["limit"] ),
			$params["search"],
			$params["columns"],
			$params["order"]
		);
		$userData = [];
		$this->getOutput()->enableOOUI();
		foreach ( $users as $user ) {
			$email = htmlspecialchars( $user->user_email, ENT_QUOTES );
			$user_name = htmlspecialchars( $user->user_name, ENT_QUOTES );
			if ( $this->emailAuthorizationService->isEmailAuthorized( $email ) ) {
				$authorized = new IconWidget( [
					'icon' => 'check',
					'framed' => false
				] );
			} else {
				$authorized = new IconWidget( [
					'icon' => 'close',
					'framed' => false,
					'flags' => [ 'destructive' ]

				] );
			}
			$userData[] = [
				"email" => $email,
				"userName" => $user_name,
				"realName" => htmlspecialchars( $user->user_real_name, ENT_QUOTES ),
				"userPage" => $this->parse( "[[User:$user_name]]" ),
				"authorized" => $authorized
			];
		}
		$filteredUserCount = count( $users );
		if ( is_string( $params["search"] ) && strlen( $params["search"] ) > 0 ) {
			$userCount = $this->emailAuthorizationStore->getUsersCount();
		} else {
			$userCount = $filteredUserCount;
		}
		return [
			"draw" => intval( $params["draw"] ),
			"recordsTotal" => $userCount,
			"recordsFiltered" => $filteredUserCount,
			"data" => $userData
		];
	}

	public function getExamplesMessages(): array {
		return [
			"action={$this->getModuleName()}&draw=1" =>
			"apihelp-{$this->getModuleName()}-standard-example"
		];
	}
}
