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

use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IResultWrapper;

class ApiEmailAuthorizationAuthorized extends ApiEmailAuthorizationBase {

	public function __construct( $main, $action, ParserFactory $parserFactory ) {
		parent::__construct( $main, $action, $parserFactory );
	}

	public function getAllowedParams(): array {
		$allowedParams = parent::getAllowedParams();
		$allowedParams["columns"] = [
			ParamValidator::PARAM_ISMULTI => true,
			ParamValidator::PARAM_DEFAULT => "email|userNames|realNames|userPages"
		];
		return $allowedParams;
	}

	public function executeBody( $params ): array {
		$authorized = $this->getAuthorized(
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
				$users = $this->getUserInfo( $email );
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
			$authorizedCount = $this->getAllAuthorizedCount();
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

	private function getAllAuthorizedCount(): int {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->estimateRowCount( 'emailauth' );
	}

	private function getAuthorized(
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
						return "emailauth.email $matches[2]";
					case 'userNames':
						return "user.user_name $matches[2]";
					case 'realNames':
						return "user.user_real_name $matches[2]";
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
			$orderOptions = 'emailauth.email asc';
		}
		if ( strlen( $contains ) > 0 ) {
			$likeClause = $dbr->buildLike( $dbr->anyString(), $contains, $dbr->anyString() );
			$conds = $dbr->makeList( [
				"emailauth.email $likeClause",
				"user.user_name $likeClause",
				"user.user_real_name $likeClause"
			], $dbr::LIST_OR );
		} else {
			$conds = "";
		}
		$tables = [
			'emailauth',
			'user'
		];
		$joinConds = [
			'user' => [
				'LEFT JOIN',
				'emailauth.email = user.user_email'
			]
		];
		return $dbr->select(
			$tables,
			[
				'emailauth.email'
			],
			$conds,
			__METHOD__,
			[
				'ORDER BY' => $orderOptions,
				'LIMIT' => $limit,
				'OFFSET' => $offset,
				'DISTINCT'
			],
			$joinConds
		);
	}

	/**
	 * @param string $email
	 * @return IResultWrapper
	 */
	private function getUserInfo( string $email ): IResultWrapper {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->select(
			'user',
			[
				'user_name',
				'user_real_name'
			],
			[
				'user_email' => $email
			],
			__METHOD__,
			[
				'ORDER BY' => 'user_name',
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
