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

use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserGroupManager;
use User;

class EmailAuthorizationService {
	public const CONSTRUCTOR_OPTIONS = [
		'EmailAuthorization_AuthorizedGroups'
	];

	/**
	 * @var EmailAuthorizationStore
	 */
	private $emailAuthorizationStore;

	/**
	 * @var array
	 */
	private $authorizedGroups;

	/**
	 * @var UserGroupManager
	 */
	private $userGroupManager;

	/**
	 * @param ServiceOptions $options
	 * @param EmailAuthorizationStore $emailAuthorizationStore
	 * @param UserGroupManager $userGroupManager
	 */
	public function __construct(
		ServiceOptions $options,
		EmailAuthorizationStore $emailAuthorizationStore,
		UserGroupManager $userGroupManager
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->authorizedGroups = $options->get( 'EmailAuthorization_AuthorizedGroups' );
		$this->emailAuthorizationStore = $emailAuthorizationStore;
		$this->userGroupManager = $userGroupManager;
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	public function isUserAuthorized( User $user ): bool {
		return $this->isEmailAuthorized( $user->getEmail() ) || $this->isUserGroupAuthorized( $user );
	}

	/**
	 * @param string|null $email
	 * @return bool
	 */
	private function isEmailAuthorized( ?string $email ): bool {
		$authorized = $this->emailAuthorizationStore->isEmailAuthorized( $email );
		if ( $authorized ) {
			return true;
		}
		$index = strpos( $email, '@' );
		if ( $index !== false && $index < strlen( $email ) - 1 ) {
			$domain = substr( $email, $index );
			return $this->emailAuthorizationStore->isEmailAuthorized( $domain );
		}
		return false;
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	private function isUserGroupAuthorized( User $user ): bool {
		$memberships = $this->userGroupManager->getUserGroupMemberships( $user );
		foreach ( $this->authorizedGroups as $group ) {
			if ( isset( $memberships[ $group ] ) && !$memberships[ $group ]->isExpired() ) {
				return true;
			}
		}
		return false;
	}
}
