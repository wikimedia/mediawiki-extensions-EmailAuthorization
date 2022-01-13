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

use ApiBase;
use ApiMain;
use ApiUsageException;
use Parser;
use ParserFactory;
use ParserOptions;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\NumericDef;

abstract class ApiEmailAuthorizationBase extends ApiBase {

	/**
	 * @var EmailAuthorizationStore
	 */
	protected $emailAuthorizationStore;

	/**
	 * @var Parser
	 */
	private $parser;

	/**
	 * @var ParserOptions
	 */
	private $parserOptions;

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
		parent::__construct( $main, $action );
		$this->emailAuthorizationStore = $emailAuthorizationStore;
		$this->parser = $parserFactory->create();
		$this->parserOptions = ParserOptions::newCanonical( $this->getContext() );
		$this->parserOptions->setOption( 'enableLimitReport', false );
	}

	/**
	 * @return array
	 */
	public function getAllowedParams(): array {
		return [
			"draw" => [
				ParamValidator::PARAM_TYPE => "integer",
				ParamValidator::PARAM_REQUIRED => true
			],
			"offset" => [
				ParamValidator::PARAM_TYPE => "integer",
				NumericDef::PARAM_MIN => 0,
				ParamValidator::PARAM_DEFAULT => 0
			],
			"limit" => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => "limit",
				NumericDef::PARAM_MIN => 1,
				NumericDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				NumericDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			"search" => [
				ParamValidator::PARAM_TYPE => "string",
				ParamValidator::PARAM_DEFAULT => ""
			],
			"order" => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_DEFAULT => ""
			]
		];
	}

	/**
	 * @throws ApiUsageException
	 */
	public function execute() {
		if ( !$this->getUser()->isAllowed( 'emailauthorizationconfig' ) ) {
			$this->dieWithError( 'emailauthorization-api-error-permissions' );
		}
		$result = $this->executeBody( $this->extractRequestParams() );
		if ( $result !== null ) {
			$this->getResult()->addValue( null, $this->getModuleName(), $result );
		}
	}

	/**
	 * @param array $params
	 * @return array
	 */
	abstract protected function executeBody( array $params ): array;

	/**
	 * @param string $wikitext
	 * @return string
	 */
	protected function parse( string $wikitext ): string {
		return $this->parser
			->parse( $wikitext, $this->getTitle(), $this->parserOptions )
			->getText( [ 'wrapperDivClass' => '' ] );
	}
}
