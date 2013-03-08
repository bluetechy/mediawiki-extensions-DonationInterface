<?php
/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 */

class PaypalGateway extends GatewayForm {

	/**
	 * Constructor - set up the new special page
	 */
	public function __construct() {
		$this->adapter = new PaypalAdapter();
		parent::__construct(); //the next layer up will know who we are.
	}

	/**
	 * Show the special page
	 *
	 * @todo
	 * - Finish error handling
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		$this->getOutput()->allowClickjacking();

		$this->setHeaders();

		$redirect = false;
		if ( $this->getRequest()->getText( 'redirect', 0 ) ) {
			$redirect = true;
		}
		if ( $this->validateForm() ) {
			$form_errors = $this->adapter->getValidationErrors();
			if ( !array_diff( array( 'currency_code' ), array_keys( $form_errors ) ) ) {
				// If the currency is invalid, fallback to USD and allow redirect.
				$this->adapter->addData( array(
					'amount' => "0.00",
					'currency_code' => 'USD',
				) );
				$this->adapter->revalidate();
			} else {
				$redirect = false;
			}
		}
		if ( $redirect ) {
			if ( $this->getRequest()->getText( 'recurring', 0 ) ) {
				$result = $this->adapter->do_transaction( 'DonateRecurring' );
			} else {
				$result = $this->adapter->do_transaction( 'Donate' );
			}

			if ( !empty( $result['redirect'] ) ) {
				$this->getOutput()->redirect( $result['redirect'] );
			}
		}

		$this->displayForm();
	}
}
