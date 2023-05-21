<?php

namespace MediaWiki\Extension\EmailAuthorization\Test;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\EmailAuthorization\EmailAuthorizationService;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\EmailAuthorization\EmailAuthorizationService::isUserAuthorized()
 * @group Database
 */
class EmailAuthorizationServiceTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->tablesUsed = [ 'emailauth' ];
	}

	public static function provideIsUserAuthorized() {
		yield [
			false,
			false,
			[],
			[],
			false,
			"no authorized emails or domains, no authorized groups"
		];
		yield [
			true,
			false,
			[],
			[],
			true,
			"user's email is authorized, not domain, no authorized groups"
		];
		yield [
			false,
			true,
			[],
			[],
			true,
			"user's domain is authorized, not email, no authorized groups"
		];
		yield [
			true,
			true,
			[],
			[],
			true,
			"user's email and domain are authorized, no authorized groups"
		];
		yield [
			false,
			false,
			[],
			[ 'testgroup' ],
			false,
			"user is in no groups, testgroup is authorized, no emails or domains authorized"
		];
		yield [
			false,
			false,
			[ 'testgroup' ],
			[],
			false,
			"user is in testgroup, no groups are authorized, no emails or domains authorized"
		];
		yield [
			false,
			false,
			[ 'testgroup' ],
			[ 'othergroup' ],
			false,
			"user is in testgroup, othergroup is authorized, no emails or domains authorized"
		];
		yield [
			false,
			false,
			[ 'testgroup' ],
			[ 'testgroup' ],
			true,
			"user is in testgroup, testgroup is authorized, no emails or domains authorized"
		];
	}

	/**
	 * @covers \MediaWiki\Extension\EmailAuthorization\EmailAuthorizationService::isUserAuthorized()
	 * @dataProvider provideIsUserAuthorized
	 */
	public function testIsUserAuthorized(
		bool $authorizeEmail,
		bool $authorizeDomain,
		array $testUserGroups,
		array $authorizedGroups,
		bool $expected,
		string $message
	) {
		$user = self::getTestUser( $testUserGroups )->getUser();

		$store = $this->getServiceContainer()->getService( 'EmailAuthorizationStore' );

		if ( $authorizeEmail ) {
			$store->insertEmail( $user->getEmail() );
		}

		if ( $authorizeDomain ) {
			$email = $user->getEmail();
			$domain = substr( $email, strpos( $email, '@' ) );
			$store->insertEmail( $domain );
		}

		$options = new ServiceOptions( EmailAuthorizationService::CONSTRUCTOR_OPTIONS, [
			'EmailAuthorization_AuthorizedGroups' => $authorizedGroups
		] );

		$service = new EmailAuthorizationService(
			$options,
			$store,
			$this->getServiceContainer()->getUserGroupManager(),
			$this->getServiceContainer()->getUserFactory()
		);
		$this->assertEquals( $expected, $service->isUserAuthorized( $user ), $message );
	}
}
