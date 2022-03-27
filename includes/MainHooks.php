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
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\PluggableAuth\Hook\PluggableAuthUserAuthorization;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\User\UserIdentity;

class MainHooks implements SpecialPage_initListHook, PluggableAuthUserAuthorization {
	public const CONSTRUCTOR_OPTIONS = [
		'EmailAuthorization_EnableRequests'
	];

	/**
	 * @var bool
	 */
	private $enableRequests;

	/**
	 * @var EmailAuthorizationService
	 */
	private $emailAuthorizationService;

	/**
	 * @param Config $config
	 * @param EmailAuthorizationService $emailAuthorizationService
	 */
	public function __construct(
		Config $config,
		EmailAuthorizationService $emailAuthorizationService
	) {
		$options = new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $config );
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->enableRequests = $options->get( 'EmailAuthorization_EnableRequests' );
		$this->emailAuthorizationService = $emailAuthorizationService;
	}

	/**
	 * @param array &$list
	 * @return bool|void
	 * @phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	 */
	public function onSpecialPage_initList( &$list ) {
		if ( !$this->enableRequests ) {
			unset( $list['EmailAuthorizationRequest'] );
			unset( $list['EmailAuthorizationApprove'] );
		}
	}

	/**
	 * @param UserIdentity $user
	 * @param bool &$authorized
	 * @return bool
	 */
	public function onPluggableAuthUserAuthorization( UserIdentity $user, bool &$authorized ): bool {
		$authorized = $this->emailAuthorizationService->isUserAuthorized( $user );
		return $authorized;
	}
}
