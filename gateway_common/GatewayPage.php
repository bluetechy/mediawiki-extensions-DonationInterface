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
 * GatewayPage
 * This class is the generic unlisted special page in charge of actually 
 * displaying the form. Each gateway will have one or more direct descendants of 
 * this class, with most of the gateway-specific control logic in its handleRequest
 * function. For instance: extensions/DonationInterface/globalcollect_gateway/globalcollect_gateway.body.php
 *
 */
abstract class GatewayPage extends UnlistedSpecialPage {
	/**
	 * An array of form errors
	 * @var array $errors
	 */
	public $errors = array( );

	/**
	 * The gateway adapter object
	 * @var object $adapter
	 */
	public $adapter;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->getOutput()->addModules( 'donationInterface.skinOverride' );
		
		$me = get_called_class();
		parent::__construct( $me );
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		global $wgContributionTrackingFundraiserMaintenance, $wgContributionTrackingFundraiserMaintenanceUnsched;

		if( $wgContributionTrackingFundraiserMaintenance
			|| $wgContributionTrackingFundraiserMaintenanceUnsched ){
			$this->getOutput()->redirect( Title::newFromText('Special:FundraiserMaintenance')->getFullURL(), '302' );
			return;
		}
		$this->handleRequest();
	}

	/**
	 * Should be overridden in each derived class to actually handle the request
	 * Performs gateway-specific checks and either redirects or displays form.
	 */
	protected abstract function handleRequest();

	/**
	 * Checks current dataset for validation errors
	 * TODO: As with every other bit of gateway-related logic that should 
	 * definitely be available to every entry point, and functionally has very 
	 * little to do with being contained within what in an ideal world would be 
	 * a piece of mostly UI, this function needs to be moved inside the gateway 
	 * adapter class.
	 * @param array	$options
	 *   OPTIONAL - In addition to all non-optional validation which verifies 
	 *   that all populated fields contain an appropriate data type, you may 
	 *   require certain field groups to be non-empty.
	 *   - address - Validation requires non-empty: street, city, state, zip
	 *   - amount - Validation requires non-empty: amount
	 *   - creditCard - Validation requires non-empty: card_num, cvv, expiration and card_type
	 *   - email - Validation requires non-empty: email
	 *   - name - Validation requires non-empty: fname, lname
	 *
	 * @return boolean Returns false on an error-free validation, otherwise true.
	 */
	public function validateForm( $options = array() ) {
		
		$check_not_empty = array();
		
		foreach ( $options as $option ){
			$add_checks = array();
			switch( $option ){
				case 'address' :
					$add_checks = array(
						'street',
						'city',
						'state',
						'country',
						'zip', //this should really be added or removed, depending on the country and/or gateway requirements. 
						//however, that's not happening in this class in the code I'm replacing, so... 
						//TODO: Something clever in the DataValidator with data groups like these. 
					);
					break;
				case 'amount' :
					$add_checks[] = 'amount';
					break;
				case 'creditCard' :
					$add_checks = array(
						'card_num',
						'cvv',
						'expiration',
						'card_type'
					);
					break;
				case 'email' :
					$add_checks[] = 'email';
					break;
				case 'name' :
					$add_checks = array(
						'fname',
						'lname'
					);
					break;
			}
			$check_not_empty = array_merge( $check_not_empty, $add_checks );
		}

		$validated_ok = $this->adapter->revalidate( $check_not_empty );

		$converted = $this->fallbackToDefaultCurrency();
		if ( $converted ) {
			$validated_ok = $this->adapter->revalidate( $check_not_empty );
			$notify = $this->adapter->getGlobal( 'NotifyOnConvert' );

			if ( $notify || !$validated_ok ) {
				$this->adapter->addManualError( array(
					'general' => $this->msg( 'donate_interface-fallback-currency-notice', 
											 $this->adapter->getGlobal( 'FallbackCurrency' ) )->text()
				) );
				$validated_ok = false;
			}
		}

		return !$validated_ok;
	}

	/**
	 * Build and display form to user
	 *
	 * The message at the top of the form can be edited in the payflow_gateway.i18n.php file
	 */
	public function displayForm() {
		global $wgOut;

		$form_class = $this->getFormClass();
		// TODO: use interface.  static ctor.
		if ( $form_class && class_exists( $form_class ) ){
			$form_obj = new $form_class( $this->adapter );
			$form = $form_obj->getForm();
			$wgOut->addHTML( $form );
		} else {

			$page = $this->adapter->getGlobal( "FailPage" );

			$log_message = '"Redirecting to [ ' . $page . ' ] "';
			$this->log( $log_message, LOG_INFO );

			if ( $page ) {

				$language = $this->getRequest()->getVal( 'language' );
				$page = wfAppendQuery( $page, array( 'uselang' => $language ) );
			}

			$wgOut->redirect( $page );
		}
	}

	/**
	 * Get the currently set form class
	 * @return mixed string containing the valid and enabled form class, otherwise false. 
	 */
	public function getFormClass() {
		return $this->adapter->getFormClass();
	}

	/**
	 * displayResultsForDebug
	 *
	 * Displays useful information for debugging purposes.
	 * Enable with $wgDonationInterfaceDisplayDebug, or the adapter equivalent.
	 * @param array $results
	 * @return null
	 */
	protected function displayResultsForDebug( $results = array() ) {
		global $wgOut;
		
		$results = empty( $results ) ? $this->adapter->getTransactionAllResults() : $results;
		
		if ( $this->adapter->getGlobal( 'DisplayDebug' ) !== true ){
			return;
		}
		$wgOut->addHTML( HTML::element( 'span', null, $results['message'] ) );

		if ( !empty( $results['errors'] ) ) {
			$wgOut->addHTML( HTML::openElement( 'ul' ) );
			foreach ( $results['errors'] as $code => $value ) {
				$wgOut->addHTML( HTML::element('li', null, "Error $code: $value" ) );
			}
			$wgOut->addHTML( HTML::closeElement( 'ul' ) );
		}

		if ( !empty( $results['data'] ) ) {
			$wgOut->addHTML( HTML::openElement( 'ul' ) );
			foreach ( $results['data'] as $key => $value ) {
				if ( is_array( $value ) ) {
					$wgOut->addHTML( HTML::openElement('li', null, $key ) . HTML::openElement( 'ul' ) );
					foreach ( $value as $key2 => $val2 ) {
						$wgOut->addHTML( HTML::element('li', null, "$key2: $val2" ) );
					}
					$wgOut->addHTML( HTML::closeElement( 'ul' ) . HTML::closeElement( 'li' ) );
				} else {
					$wgOut->addHTML( HTML::element('li', null, "$key: $value" ) );
				}
			}
			$wgOut->addHTML( HTML::closeElement( 'ul' ) );
		} else {
			$wgOut->addHTML( "Empty Results" );
		}
		if ( array_key_exists( 'Donor', $_SESSION ) ) {
			$wgOut->addHTML( "Session Donor Vars:" . HTML::openElement( 'ul' ));
			foreach ( $_SESSION['Donor'] as $key => $val ) {
				$wgOut->addHTML( HTML::element('li', null, "$key: $val" ) );
			}
			$wgOut->addHTML( HTML::closeElement( 'ul' ) );
		} else {
			$wgOut->addHTML( "No Session Donor Vars:" );
		}

		if ( is_array( $this->adapter->debugarray ) ) {
			$wgOut->addHTML( "Debug Array:" . HTML::openElement( 'ul' ) );
			foreach ( $this->adapter->debugarray as $val ) {
				$wgOut->addHTML( HTML::element('li', null, $val ) );
			}
			$wgOut->addHTML( HTML::closeElement( 'ul' ) );
		} else {
			$wgOut->addHTML( "No Debug Array" );
		}
	}

	/**
	 * logs messages to the current gateway adapter's configured log location
	 * @param string $msg The message to log
	 * @param int|string $log_level The severity level of the message.
	 */
	public function log( $msg, $log_level=LOG_INFO ) {
		$this->adapter->log( $msg, $log_level );
	}

	/**
	 * Fetch the array of iso country codes => country names
	 * @return array
	 */
	public static function getCountries() {
		require_once( dirname( __FILE__ ) . '/../gateway_forms/includes/countryCodes.inc' );
		return countryCodes();
	}

	/**
	 * Handle the result from the gateway
	 *
	 * If there are errors, then this will return to the form.
	 *
	 * @todo
	 * - This is being implemented in GlobalCollect
	 * - Do we need to implement this for PayFlow Pro? Not yet!
	 * - Do we only want to skip the Thank you page on getFinalStatus() => failed?
	 *
	 * @return null
	 */
	protected function resultHandler() {
		
		global $wgOut;

		// If transaction is anything, except failed, go to the thank you page.
		
		if ( in_array( $this->adapter->getFinalStatus(), $this->adapter->getGoToThankYouOn() ) ) {

			$thankyoupage = $this->adapter->getThankYouPage();
	
			if ( $thankyoupage ) {
				
				$queryString = '?payment_method=' . $this->adapter->getPaymentMethod() . '&payment_submethod=' . $this->adapter->getPaymentSubmethod();
				
				return $wgOut->redirect( $thankyoupage . $queryString );
			}
		}
		
		// If we did not go to the Thank you page, there must be an error.
		return $this->resultHandlerError();
	}

	/**
	 * Handle the error result from the gateway
	 *
	 * @todo
	 * - logging may need be added to this method
	 *
	 * @return null
	 */
	protected function resultHandlerError() {

		// Display debugging results
		$this->displayResultsForDebug();

		foreach ( $this->adapter->getTransactionErrors() as $code => $message ) {
			
			$error = array();
			if ( strpos( $code, 'internal' ) === 0 ) {
				$error['retryMsg'][ $code ] = $message;
			}
			else {
				$error['general'][ $code ] = $message;
			}
			$this->adapter->addManualError( $error );
		}
		
		return $this->displayForm();
	}

	/**
	 * If a currency code error exists and fallback currency conversion is 
	 * enabled for this adapter, convert intended amount to default currency.
	 *
	 * @return boolean whether currency conversion was performed
	 */
	protected function fallbackToDefaultCurrency() {
		$defaultCurrency = $this->adapter->getGlobal( 'FallbackCurrency' );
		if ( !$defaultCurrency ) {
			return false;
		}
		$form_errors = $this->adapter->getValidationErrors();
		if ( !$form_errors || !array_key_exists( 'currency_code', $form_errors ) ) {
			return false;
		}
		// If the currency is invalid, fallback to default.
		// Our conversion rates are all relative to USD, so use that as an
		// intermediate currency if converting between two others.
		$oldCurrency = $this->adapter->getData_Unstaged_Escaped( 'currency_code' );
		if ( $oldCurrency === $defaultCurrency ) {
			$adapterClass = $this->adapter->getGatewayAdapterClass();
			throw new MWException( __FUNCTION__ . " Unsupported currency $defaultCurrency set as fallback for $adapterClass." );
		}
		$oldAmount = $this->adapter->getData_Unstaged_Escaped( 'amount' );
		$usdAmount = 0.0;
		$newAmount = 0;

		require_once( __DIR__ . '/../gateway_common/currencyRates.inc' );
		$conversionRates = getCurrencyRates();
		if ( $oldCurrency === 'USD' ) {
			$usdAmount = $oldAmount;
		}
		elseif ( array_key_exists( $oldCurrency, $conversionRates ) ) {
			$usdAmount = $oldAmount / $conversionRates[$oldCurrency];
		}

		if ( $defaultCurrency === 'USD' ) {
			$newAmount = floor( $usdAmount );
		}
		elseif ( array_key_exists( $defaultCurrency, $conversionRates ) ) {
			$newAmount = floor( $usdAmount * $conversionRates[$defaultCurrency] );
		}

		$this->adapter->addData( array(
			'amount' => $newAmount,
			'currency_code' => $defaultCurrency
		) );

		$this->adapter->log( "Unsupported currency $oldCurrency forced to $defaultCurrency" );
		return true;
	}

}