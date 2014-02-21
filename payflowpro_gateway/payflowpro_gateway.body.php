<?php

class PayflowProGateway extends GatewayForm {
	/**
	 * Constructor - set up the new special page
	 */
	public function __construct() {
		$this->adapter = new PayflowProAdapter();
		parent::__construct(); //the next layer up will know who we are.
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		$this->setHeaders();

		/**
		 *  handle PayPal redirection
		 *
		 *  if paypal redirection is enabled ($wgPayflowProGatewayPaypalURL must be defined)
		 *  and the PaypalRedirect form value must be true
		 */
		if ( $this->getRequest()->getText( 'PaypalRedirect', 0 ) ) {
			$this->paypalRedirect();
			return;
		}
		
		// dispatch forms/handling
		if ( $this->adapter->checkTokens() ) {
			if ( $this->adapter->posted ) {
				// The form was submitted and the payment method has been set
				// Check form for errors
				$form_errors = $this->validateForm();
				// If there were errors, redisplay form, otherwise proceed to next step
				if ( $form_errors ) {
					$this->displayForm();
				} else { // The submitted form data is valid, so process it
					$result = $this->adapter->do_transaction( 'Card' );

					// if the transaction was flagged for rejection
					if ( $this->adapter->getValidationAction() == 'reject' ) {
						$this->fnPayflowDisplayDeclinedResults( '' );
					}

					if ( $this->adapter->getValidationAction() == 'process' ) {
						$this->fnPayflowDisplayResults( $result );
					}
					$this->displayResultsForDebug( $result );
				}
			} else {
				// Display form for the first time
				$this->displayForm();
			}
		} else {//token mismatch
			$error['general']['token-mismatch'] = $this->msg( 'donate_interface-token-mismatch' )->text();
			$this->adapter->addManualError( $error );
			$this->displayForm();
		}
	}

	/**
	 * "Reads" the name-value pair result string returned by Payflow and creates corresponding error messages
	 *
	 * @param $result String: name-value pair results returned by Payflow
	 *
	 * Credit: code modified from payflowpro_example_EC.php posted (and supervised) on the PayPal developers message board
	 */
	private function fnPayflowDisplayResults( $result ) {
		if ( is_array( $result ) && array_key_exists( 'errors', $result ) && is_array( $result['errors'] ) ) {
			foreach ( $result['errors'] as $key => $value ) {
				$errorCode = $key;
				$responseMsg = $value;
				break; //we just want the top, and this is probably the fastest way.
			}
		}

		$data = $this->adapter->getData_Unstaged_Escaped();
		$msgPrefix = $data['order_id'] . ' ';

		// if approved, display results and send transaction to the queue
		if ( $errorCode == '1' ) {
			$this->log( $msgPrefix . "Transaction approved.", LOG_DEBUG );
			$this->fnPayflowDisplayApprovedResults( $data, $responseMsg );
			// give user a second chance to enter incorrect data
		} elseif ( ( $errorCode == '3' ) && ( $data['numAttempt'] < '5' ) ) {
			$this->log( $msgPrefix . "Transaction unsuccessful (invalid info).", LOG_DEBUG );
			// pass responseMsg as an array key as required by displayForm
			$error['retryMsg'] = $responseMsg;
			$this->adapter->addManualError( $error );
			$this->displayForm();
			// if declined or if user has already made two attempts, decline
		} elseif ( ( $errorCode == '2' ) || ( $data['numAttempt'] >= '3' ) ) {
			$this->log( $msgPrefix . "Transaction declined.", LOG_DEBUG );
			$this->fnPayflowDisplayDeclinedResults( $responseMsg );
		} elseif ( ( $errorCode == '4' ) ) {
			$this->log( $msgPrefix . "Transaction unsuccessful.", LOG_DEBUG );
			$this->fnPayflowDisplayOtherResults( $responseMsg );
		} elseif ( ( $errorCode == '5' ) ) {
			$this->log( $msgPrefix . "Transaction pending.", LOG_DEBUG );
			$this->fnPayflowDisplayPending( $data, $responseMsg );
		} elseif ( strpos( $errorCode, 'internal' ) === 0 ) {
			$this->log( $msgPrefix . "Transaction unsuccessful (communication failure).", LOG_DEBUG );
			$error['retryMsg'] = $responseMsg;
			$this->adapter->addManualError( $error );
			$this->displayForm();
		} elseif ( !empty( $errorCode ) ) {
			// This should not be hit.
			$this->log( $msgPrefix . "Transaction unsuccessful (unknown failure).", LOG_DEBUG );
			$this->fnPayflowDisplayOtherResults( $responseMsg );
			$error['retryMsg'] = $errorCode;
			$this->adapter->addManualError( $error );
			$this->displayForm();
		}
	}

	/**
	 * Display response message to user with submitted user-supplied data
	 *
	 * @param $data Array: array of posted data from form
	 * @param $responseMsg String: message supplied by fnPayflowDisplayResults function
	 */
	function fnPayflowDisplayApprovedResults( $data, $responseMsg ) {
		$thankyoupage = $this->adapter->getGlobal( 'ThankYouPage' );

		if ( $thankyoupage ) {
			$this->getOutput()->redirect( $thankyoupage );
		} else {
			// display response message
			$this->getOutput()->addHTML( '<h3 class="response_message">' . htmlspecialchars( $responseMsg, ENT_QUOTES ) . '</h3>' );

			// translate country code into text
			$countries = GatewayForm::getCountries();

			$rows = array(
				'title' => array( $this->msg( 'donate_interface-post-transaction' )->text() ),
				'amount' => array( $this->msg( 'donate_interface-donor-amount' )->text(), $data['amount'] ),
				'email' => array( $this->msg( 'donate_interface-donor-email' )->text(), $data['email'] ),
				'name' => array( $this->msg( 'donate_interface-donor-name' )->text(), $data['fname'], $data['lname'] ),
				'address' => array( $this->msg( 'donate_interface-donor-address' )->text(), $data['street'], $data['city'], $data['state'], $data['zip'], $countries[$data['country']] ),
			);

			// if we want to show the response
			$this->getOutput()->addHTML( Xml::buildTable( $rows, array( 'class' => 'submitted-response' ) ) );
		}
	}

	/**
	 * Display response message to user with submitted user-supplied data
	 *
	 * @param $responseMsg string supplied by fnPayflowDisplayResults function (should be HTML)
	 */
	function fnPayflowDisplayDeclinedResults( $responseMsg ) {
		$failpage = $this->adapter->getFailPage();

		if ( $failpage ) {
			$this->getOutput()->redirect( $failpage );
		} else {
			// general decline message
			$declinedDefault = $this->msg( 'php-response-declined' )->escaped();

			// display response message
			$this->getOutput()->addHTML( '<h3 class="response_message">' . $declinedDefault . ' ' . htmlspecialchars( $responseMsg, ENT_QUOTES ) . '</h3>' );
		}
	}

	/**
	 * Display response message when there is a system error unrelated to user's entry
	 *
	 * @param $responseMsg String: message supplied by fnPayflowDisplayResults function
	 */
	function fnPayflowDisplayOtherResults( $responseMsg ) {
		//I have collapsed it like this because the contents were identical.
		// @todo Determine if we need to be switching on anything else in the display here.
		$this->fnPayflowDisplayDeclinedResults( $responseMsg );
	}

	function fnPayflowDisplayPending( $responseMsg ) {
		$thankyou = $this->msg( 'donate_interface-thankyou' )->escaped();

		// display response message
		$this->getOutput()->addHTML( '<h2 class="response_message">' . $thankyou . '</h2>' );
		$this->getOutput()->addHTML( '<p>' . $responseMsg );
	}
}
