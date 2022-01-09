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

use OOUI\IconWidget;
use ParserFactory;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IResultWrapper;

class ApiEmailAuthorizationUsers extends ApiEmailAuthorizationBase {

	public function __construct( $main, $action, ParserFactory $parserFactory ) {
		parent::__construct( $main, $action, $parserFactory );
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
		$users = $this->getUsers(
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
			if ( EmailAuthorization::isEmailAuthorized( $email ) ) {
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
			$userCount = $this->getAllUsersCount();
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

	private function getAllUsersCount(): int {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->estimateRowCount( 'user' );
	}

	private function getUsers(
		string $offset,
		string $limit,
		string $contains,
		array $columns,
		array $order
	): IResultWrapper {
		$dbr = wfGetDB( DB_REPLICA );
		$orderOptions = array_map( static function ( $orderOption ) use ( $columns ) {
			$validOption = preg_match( "/(\d+)(asc|desc)/i", $orderOption, $matches );
			if ( $validOption === 1 ) {
				switch ( $columns[intval( $matches[1] )] ) {
					case 'email':
						return "user_email $matches[2]";
					case 'userName':
						return "user_name $matches[2]";
					case 'realName':
						return "user_real_name $matches[2]";
					default:
						return '';
				}
			} else {
				return '';
			}
		}, $order );
		$orderOptions = array_filter( $orderOptions );
		$orderOptions = implode( ', ', $orderOptions );
		if ( $orderOptions === '' ) {
			$orderOptions = 'user_email asc';
		}
		if ( strlen( $contains ) > 0 ) {
			$likeClause = $dbr->buildLike( $dbr->anyString(), $contains, $dbr->anyString() );
			$conds = $dbr->makeList( [
				"user_name $likeClause",
				"user_real_name $likeClause",
				"user_email $likeClause"
			], $dbr::LIST_OR );
		} else {
			$conds = "";
		}
		return $dbr->select(
			'user',
			[
				'user_name',
				'user_real_name',
				'user_email'
			],
			$conds,
			__METHOD__,
			[
				'ORDER BY' => $orderOptions,
				'LIMIT' => $limit,
				'OFFSET' => $offset
			]
		);
	}

	public function getExamplesMessages(): array {
		return [
			"action={$this->getModuleName()}&draw=1" =>
			"apihelp-{$this->getModuleName()}-standard-example"
		];
	}
}
