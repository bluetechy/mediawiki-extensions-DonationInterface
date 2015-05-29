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
use \Psr\Log\LogLevel;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group Astropay
 */
class DonationInterface_Adapter_Astropay_AstropayTest extends DonationInterfaceTestCase {

	/**
	 * @param $name string The name of the test case
	 * @param $data array Any parameters read from a dataProvider
	 * @param $dataName string|int The name or index of the data set
	 */
	function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = 'TestingAstropayAdapter';
	}

	function setUp() {
		parent::setUp();
		$this->setMwGlobals( array(
			'wgAstropayGatewayEnabled' => true,
		) );
	}

	function tearDown() {
		TestingAstropayAdapter::clearGlobalsCache();
		parent::tearDown();
	}

	/**
	 * Ensure we're setting the right url for each transaction
	 * @covers AstropayAdapter::getCurlBaseOpts
	 */
	function testCurlUrl() {
		$init = $this->getDonorTestData( 'BR' );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->setCurrentTransaction( 'NewInvoice' );

		$result = $gateway->getCurlBaseOpts();

		$this->assertEquals(
			'https://sandbox.astropay.example.com/api_curl/streamline/NewInvoice',
			$result[CURLOPT_URL],
			'Not setting URL to transaction-specific value.'
		);
	}

	/**
	 * Test the NewInvoice transaction is making a sane request and signing
	 * it correctly
	 */
	function testNewInvoiceRequest() {
		$init = $this->getDonorTestData( 'BR' );
		$this->setLanguage( $init['language'] );
		$_SESSION['Donor']['order_id'] = '123456789';
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'NewInvoice' );
		parse_str( $gateway->curled[0], $actual );

		$expected = array(
			'x_login' => 'createlogin',
			'x_trans_key' => 'createpass',
			'x_invoice' => '123456789',
			'x_amount' => '100.00',
			'x_currency' => 'BRL',
			'x_bank' => 'TE',
			'x_country' => 'BR',
			'x_description' => wfMessage( 'donate_interface-donation-description' )->inLanguage( $init['language'] )->text(),
			'x_iduser' => 'e08fa5e37586ae0fcef3',
			'x_cpf' => '00003456789',
			'x_name' => 'Nome Apelido',
			'x_email' => 'nobody@example.org',
			// 'x_address' => 'Rua Falso 123',
			// 'x_zip' => '01110-111',
			// 'x_city' => 'São Paulo',
			// 'x_state' => 'SP',
			'control' => '5F6DB223AFA42022BE8D955C7BAA7C63EF1D4386CABAC0CE665959AC59B9EDF6',
			'type' => 'json',
		);
		$this->assertEquals( $expected, $actual, 'NewInvoice is not including the right parameters' );
	}

	/**
	 * When Astropay sends back valid JSON with status "0", we should set txn
	 * status to true and errors should be empty.
	 */
	function testStatusNoErrors() {
		$init = $this->getDonorTestData( 'BR' );
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'NewInvoice' );

		$this->assertEquals( true, $gateway->getTransactionStatus(),
			'Transaction status should be true for code "0"' );

		$this->assertEmpty( $gateway->getTransactionErrors(),
			'Transaction errors should be empty for code "0"' );
	}

	/**
	 * If astropay sends back non-JSON, communication status should be false
	 */
	function testGibberishResponse() {
		$init = $this->getDonorTestData( 'BR' );
		$this->setLanguage( $init['language'] );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->setDummyGatewayResponseCode( 'notJson' );

		$gateway->do_transaction( 'NewInvoice' );

		$this->assertEquals( false, $gateway->getTransactionStatus(),
			'Transaction status should be false for bad format' );
	}

	/**
	 * When Astropay sends back valid JSON with status "1", we should set
	 * error array to generic error and log a warning.
	 */
	function testStatusErrors() {
		$init = $this->getDonorTestData( 'BR' );
		$this->setLanguage( $init['language'] );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->setDummyGatewayResponseCode( '1' );

		$gateway->do_transaction( 'NewInvoice' );

		$expected = array(
			'internal-0000' => wfMessage( 'donate_interface-processing-error')->inLanguage( $init['language'] )->text()
		);
		$this->assertEquals( $expected, $gateway->getTransactionErrors(),
			'Wrong error for code "1"' );
		$logged = $this->getLogMatches( LogLevel::WARNING, '/This error message should appear in the log.$/' );
		$this->assertNotEmpty( $logged );
	}

	/**
	 * do_transaction should set redirect key when we get a valid response.
	 */
	function testRedirectOnSuccess() {
		$init = $this->getDonorTestData( 'BR' );
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'NewInvoice' );

		// from the test response
		$expected = 'https://sandbox.astropaycard.com/go_to_bank?id=A5jvKfK1iHIRUTPXXt8lDFGaRRLzPgBg';
		$response = $gateway->getTransactionResponse();
		$this->assertEquals( $expected, $response->getRedirect(),
			'do_transaction is not setting the right redirect' );
	}

	/**
	 * do_transaction should set redirect key when we get a valid response.
	 */
	function testDoPaymentSuccess() {
		$init = $this->getDonorTestData( 'BR' );
		$init['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $init );

		$result = $gateway->doPayment();

		// from the test response
		$expected = 'https://sandbox.astropaycard.com/go_to_bank?id=A5jvKfK1iHIRUTPXXt8lDFGaRRLzPgBg';
		$this->assertEquals( $expected, $result->getRedirect(),
			'doPayment is not setting the right redirect' );
	}

	/**
	 * When Astropay sends back valid JSON with status "1", we should set
	 * error array to generic error and log a warning.
	 */
	function testDoPaymentErrors() {
		$init = $this->getDonorTestData( 'BR' );
		$this->setLanguage( $init['language'] );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->setDummyGatewayResponseCode( '1' );

		$result = $gateway->doPayment();

		$expected = array(
			'internal-0000' => array(
				'message' => wfMessage( 'donate_interface-processing-error')->inLanguage( $init['language'] )->text(),
				'debugInfo' => 'Astropay response has non-zero status 1.  Error description: This error message should appear in the log.',
				'logLevel' => LogLevel::WARNING
			)
		);
		$this->assertEquals( $expected, $result->getErrors(),
			'Wrong error array in PaymentResult' );
		// TODO: Should this really be a refresh, or should we finalize to failed here?
		$this->assertTrue( $result->getRefresh(), 'PaymentResult should be a refresh' );
	}

	/**
	 * PaymentStatus transaction should interpret the delimited response
	 */
	function testPaymentStatus() {
		$init = $this->getDonorTestData( 'BR' );
		$_SESSION['Donor']['order_id'] = '123456789';
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'PaymentStatus' );

		// from the test response
		$expected = array(
			'result' => '9',
			'x_amount' => '100.00',
			'x_iduser' => '08feb2d12771bbcfeb86',
			'x_invoice' => '123456789',
			'PT' => '1',
			'x_control' => '0656B92DF44B814D48D84FED2F444CCA1E991A24A365FBEECCCA15B73CC08C2A',
			'x_document' => '987654321',
			'x_bank' => 'TE',
			'x_payment_type' => '03',
			'x_bank_name' => 'GNB',
			'x_currency' => 'BRL',
		);
		$results = $gateway->getTransactionData();
		$this->assertEquals( $expected, $results,
			'PaymentStatus response not interpreted correctly' );
		// Should not throw exception
		$gateway->verifyStatusSignature( $results );
	}

	/**
	 * Invalid signature should be recognized as such.
	 */
	function testInvalidSignature() {
		$init = $this->getDonorTestData( 'BR' );
		$_SESSION['Donor']['order_id'] = '123456789';
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->setDummyGatewayResponseCode( 'badsig' );
		$gateway->do_transaction( 'PaymentStatus' );

		$results = $gateway->getTransactionData();
		$this->setExpectedException( 'ResponseProcessingException' );
		$gateway->verifyStatusSignature( $results );
	}

	/**
	 * If status is paid and signature is correct, processResponse should not
	 * throw exception and final status should be 'completed'
	 */
	function testSuccessfulReturn() {
		$init = $this->getDonorTestData( 'BR' );
		$_SESSION['Donor']['order_id'] = '123456789';
		$gateway = $this->getFreshGatewayObject( $init );

		// Next lines mimic Astropay resultswitcher
		$gateway->setCurrentTransaction( 'ProcessReturn' );
		$response = array(
			'result' => '9',
			'x_amount' => '100.00',
			'x_amount_usd' => '42.05',
			'x_control' => 'DDF89085AC70C0B0628150C51D64419D8592769F2439E3936570E26D24881730',
			'x_description' => 'Donation to the Wikimedia Foundation',
			'x_document' => '32869',
			'x_iduser' => '08feb2d12771bbcfeb86',
			'x_invoice' => '123456789',
		);

		$gateway->processResponse( $response );
		$status = $gateway->getFinalStatus();
		$this->assertEquals( FinalStatus::COMPLETE, $status );
	}

	/**
	 * If payment is rejected, final status should be 'failed'
	 */
	function testRejectedReturn() {
		$init = $this->getDonorTestData( 'BR' );
		$_SESSION['Donor']['order_id'] = '123456789';
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->setCurrentTransaction( 'ProcessReturn' );
		$response = array(
			'result' => '8', // rejected by bank
			'x_amount' => '100.00',
			'x_amount_usd' => '42.05',
			'x_control' => '706F57BC3E74906B14B1DEB946F027104513797CC62AC0F5107BC98F42D5DC95',
			'x_description' => 'Donation to the Wikimedia Foundation',
			'x_document' => '32869',
			'x_iduser' => '08feb2d12771bbcfeb86',
			'x_invoice' => '123456789',
		);

		$gateway->processResponse( $response );
		$status = $gateway->getFinalStatus();
		$this->assertEquals( FinalStatus::FAILED, $status );
	}

	function testStageBankCode() {
		$init = $this->getDonorTestData( 'BR' );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'elo';
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->doPayment();

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$bank_code = $exposed->getData_Staged( 'bank_code' );
		$this->assertEquals( 'EL', $bank_code, 'Not setting bank_code in doPayment' );
	}

	/**
	 * Test that we run the AntiFraud hooks before redirecting
	 */
	function testAntiFraudHooks() {
		DonationInterface_FraudFiltersTest::setupFraudMaps();
		$init = $this->getDonorTestData( 'BR' );
		$init['payment_method'] = 'cc';
		$init['bank_code'] = 'VD';
		// following data should trip fraud alarms
		$init['utm_medium'] = 'somethingmedia';
		$init['utm_source'] = 'somethingmedia';
		$init['email'] = 'somebody@wikipedia.org';

		$gateway = $this->getFreshGatewayObject( $init );

		$result = $gateway->doPayment();

		$this->assertTrue( $result->isFailed(), 'Result should be failure if fraud filters say challenge' );
		$this->assertEquals( 'challenge', $gateway->getValidationAction(), 'Validation action is not as expected' );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 60, $exposed->risk_score, 'RiskScore is not as expected' );
	}
}
