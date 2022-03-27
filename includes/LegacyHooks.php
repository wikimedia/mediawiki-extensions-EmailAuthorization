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

use MediaWiki\MediaWikiServices;

class LegacyHooks {
	/**
	 * @param array &$notifications
	 * @param array &$notificationCategories
	 * @param array &$icons
	 * @return void
	 */
	public static function onBeforeCreateEchoEvent( array &$notifications,
		array &$notificationCategories, array &$icons ) {
		$notificationCategories['emailauthorization-notification-category'] = [
			'priority' => 3
		];

		$notifications['emailauthorization-account-request'] = [
			'category' => 'emailauthorization-notification-category',
			'group' => 'positive',
			'section' => 'alert',
			'presentation-model' => EchoEAPresentationModel::class,
			'user-locators' => [ '\MediaWiki\Extension\EmailAuthorization\LegacyHooks::locateBureaucrats' ]
		];
	}

	/**
	 * @param EchoEvent $event
	 * @return array
	 */
	public static function locateBureaucrats( EchoEvent $event ): array {
		$services = MediaWikiServices::getInstance();
		$emailAuthorizationStore = $services->get( "EmailAuthorizationStore" );
		$userFactory = $services->getUserFactory();
		$res = $emailAuthorizationStore->getBureaucrats();
		$users = [];
		foreach ( $res as $row ) {
			$id = $row->ug_user;
			$user = $userFactory->newFromId( $id );
			$expiry = $row->ug_expiry;
			if ( !$expiry || wfTimestampNow() < $expiry ) {
				$users[$id] = $user;
			}
		}
		return $users;
	}
}
