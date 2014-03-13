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

/**
 * @see DonationInterfaceTestCase
 */
require_once dirname( dirname( dirname( __FILE__ ) ) ) . DIRECTORY_SEPARATOR . 'DonationInterfaceTestCase.php';

/**
 * 
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 * @group RealTimeBankTransfer
 */
class DonationInterface_Adapter_GlobalCollect_DirectDebitTestCase extends DonationInterfaceTestCase {

	/**
	 * testBuildRequestXml
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::setCurrentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData_Unstaged_Escaped
	 */
	public function testBuildRequestXmlForDirectDebitSpain() {

		$optionsForTestData = array(
			'form_name' => 'RapidHTML',
			'payment_method' => 'dd',
			'payment_submethod' => 'dd_es',
			'payment_product_id' => 709,
		);

		//somewhere else?
		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );
		unset( $options['payment_product_id'] );
		unset( $options['payment_submethod'] );

		//stash base options for later builds
		$generalOptions = $options;


		$dd_info_supplied = array (
			'branch_code' => '123',
			'account_name' => 'Henry',
			'account_number' => '21',
			'bank_code' => '37',
			'bank_check_digit' => 'BD',
			'direct_debit_text' => 'testy test test',
		);
		$dd_info_expected = array (
			'branch_code' => '00123', //5
			'account_name' => 'Henry',
			'account_number' => '0000000021', //10
			'bank_code' => '0037', //4
			'bank_check_digit' => 'BD',
			'direct_debit_text' => 'Wikimedia Foundation', //hard-coded in the gateway
		);
		$optionsForTestData = array_merge( $optionsForTestData, $dd_info_expected );
		$options = array_merge( $options, $dd_info_supplied );

		$this->buildRequestXmlForGlobalCollect( $optionsForTestData, $options );
	}
}

