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

use EchoEventPresentationModel;
use Message;
use Title;

class EchoEAPresentationModel extends EchoEventPresentationModel {

	public function getIconType(): string {
		return 'user-rights';
	}

	public function getPrimaryLink(): array {
		return [
			'url' => Title::newFromText( 'Special:EmailAuthorizationApprove' )->getFullURL(),
			'label' => $this->msg( "notification-link-label-{$this->type}" )
		];
	}

	public function getHeaderMessage(): Message {
		$msg = wfMessage( "notification-header-{$this->type}" );
		$msg->params( $this->event->getExtraParam( 'email' ) );
		return $msg;
	}

	public function getBodyMessage(): Message {
		$msg = wfMessage( "notification-body-{$this->type}" );
		$msg->params( $this->event->getExtraParam( 'email' ) );
		return $msg;
	}
}
