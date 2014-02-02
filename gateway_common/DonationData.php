<?php

/**
 * DonationData
 * This class is responsible for pulling all the data used by DonationInterface 
 * from various sources. Once pulled, DonationData will then normalize and 
 * sanitize the data for use by the various gateway adapters which connect to 
 * the payment gateways, and through those gateway adapters, the forms that 
 * provide the user interface.
 * 
 * DonationData was not written to be instantiated by anything other than a 
 * gateway adapter (or class descended from GatewayAdapter). 
 * 
 * @author khorn
 */
class DonationData {
	protected $normalized = array( );
	protected $gateway;
	protected $validationErrors = null;

	/**
	 * DonationData constructor
	 * @param GatewayAdapter $gateway
	 * @param boolean $test Indicates if DonationData has been instantiated in 
	 * testing mode. Default is false.
	 * @param mixed $data An optional array of donation data that will, if 
	 * present, circumvent the usual process of gathering the data from various 
	 * places in $wgRequest, or 'false' to gather the data the usual way. 
	 * Default is false. 
	 */
	function __construct( $gateway, $test = false, $data = false ) {
		$this->gateway = $gateway;
		$this->gatewayID = $this->getGatewayIdentifier();
		$this->populateData( $test, $data );
	}

	/**
	 * populateData, called on construct, pulls donation data from various 
	 * sources. Once the data has been pulled, it will handle any session data 
	 * if present, normalize the data regardless of the source, and handle the 
	 * caching variables.  
	 * @global Webrequest $wgRequest 
	 * @param boolean $test Indicates if DonationData has been instantiated in 
	 * testing mode. Default is false.
	 * @param mixed $external_data An optional array of donation data that will, 
	 * if present, circumvent the usual process of gathering the data from 
	 * various places in $wgRequest, or 'false' to gather the data the usual way. 
	 * Default is false. 
	 */
	protected function populateData( $test = false, $external_data = false ) {
		global $wgRequest;
		$this->normalized = array( );
		if ( is_array( $external_data ) ){
			$this->normalized = $external_data;
		} elseif ( $test ) {
			$this->populateData_Test();
		} else {
			$this->normalized = array(
				'amount' => $wgRequest->getText( 'amount', null ),
				'amountGiven' => $wgRequest->getText( 'amountGiven', null ),
				'amountOther' => $wgRequest->getText( 'amountOther', null ),
				'email' => $wgRequest->getText( 'emailAdd' ),
				'fname' => $wgRequest->getText( 'fname' ),
				'mname' => $wgRequest->getText( 'mname' ),
				'lname' => $wgRequest->getText( 'lname' ),
				'street' => $wgRequest->getText( 'street' ),
				'street_supplemental' => $wgRequest->getText( 'street_supplemental' ),
				'city' => $wgRequest->getText( 'city' ),
				'state' => $wgRequest->getText( 'state' ),
				'zip' => $wgRequest->getText( 'zip' ),
				'country' => $wgRequest->getText( 'country' ),
				'fname2' => $wgRequest->getText( 'fname' ),
				'lname2' => $wgRequest->getText( 'lname' ),
				'street2' => $wgRequest->getText( 'street' ),
				'city2' => $wgRequest->getText( 'city' ),
				'state2' => $wgRequest->getText( 'state' ),
				'zip2' => $wgRequest->getText( 'zip' ),
				/**
				 * For legacy reasons, we might get a 0-length string passed into the form for country2.  If this happens, we need to set country2
				 * to be 'country' for downstream processing (until we fully support passing in two separate addresses).  I thought about completely
				 * disabling country2 support in the forms, etc but realized there's a chance it'll be resurrected shortly.  Hence this silly hack.
				 */
				'country2' => ( strlen( $wgRequest->getText( 'country2' ) ) ) ? $wgRequest->getText( 'country2' ) : $wgRequest->getText( 'country' ),
				'size' => $wgRequest->getText( 'size' ),
				'premium_language' => $wgRequest->getText( 'premium_language', null ),
				'card_num' => str_replace( ' ', '', $wgRequest->getText( 'card_num' ) ),
				'card_type' => $wgRequest->getText( 'card_type' ),
				'expiration' => $wgRequest->getText( 'mos' ) . substr( $wgRequest->getText( 'year' ), 2, 2 ),
				'cvv' => $wgRequest->getText( 'cvv' ),
				//Leave both of the currencies here, in case something external didn't get the memo.
				'currency' => $wgRequest->getVal( 'currency' ),
				'currency_code' => $wgRequest->getVal( 'currency_code' ),
				'payment_method' => $wgRequest->getText( 'payment_method', null ),  // NOTE: If things are breaking because session data is overwriting this; please fix elsewhere!
				'payment_submethod' => $wgRequest->getText( 'payment_submethod', null ), // Used by GlobalCollect for payment types
				'paymentmethod' => $wgRequest->getText( 'paymentmethod', null ), //used by the FormChooser (and the newest banners) for some reason.
				'submethod' => $wgRequest->getText( 'submethod', null ), //same as above. Ideally, the newer banners would stop using these vars and go back to the old ones...
				'issuer_id' => $wgRequest->getText( 'issuer_id' ),
				'order_id' => $wgRequest->getText( 'order_id', null ), //as far as I know, this won't actually ever pull anything back.
				'i_order_id' => $wgRequest->getText( 'i_order_id', null ), //internal id for each contribution attempt
				'referrer' => ( $wgRequest->getVal( 'referrer' ) ) ? $wgRequest->getVal( 'referrer' ) : $wgRequest->getHeader( 'referer' ),
				'utm_source' => $wgRequest->getText( 'utm_source' ),
				'utm_source_id' => $wgRequest->getVal( 'utm_source_id', null ),
				'utm_medium' => $wgRequest->getText( 'utm_medium' ),
				'utm_campaign' => $wgRequest->getText( 'utm_campaign' ),
				'utm_key' => $wgRequest->getText( 'utm_key' ),
				// Pull both of these here. We can logic out which one to use in the normalize bits. 
				'language' => $wgRequest->getText( 'language', null ),
				'uselang' => $wgRequest->getText( 'uselang', null ),
				'comment' => $wgRequest->getText( 'comment' ),
				// test_string has been disabled - may no longer be needed.
				//'test_string' => $wgRequest->getText( 'process' ), // for showing payflow string during testing
				'_cache_' => $wgRequest->getText( '_cache_', null ),
				'token' => $wgRequest->getText( 'token', null ),
				'contribution_tracking_id' => $wgRequest->getText( 'contribution_tracking_id' ),
				'data_hash' => $wgRequest->getText( 'data_hash' ),
				'action' => $wgRequest->getText( 'action' ),
				'gateway' => $wgRequest->getText( 'gateway' ), //likely to be reset shortly by setGateway();
				'owa_session' => $wgRequest->getText( 'owa_session', null ),
				'owa_ref' => $wgRequest->getText( 'owa_ref', null ),
				'descriptor' => $wgRequest->getText( 'descriptor', null ),

				'account_name' => $wgRequest->getText( 'account_name', null ),
				'account_number' => $wgRequest->getText( 'account_number', null ),
				'authorization_id' => $wgRequest->getText( 'authorization_id', null ),
				'bank_check_digit' => $wgRequest->getText( 'bank_check_digit', null ),
				'bank_name' => $wgRequest->getText( 'bank_name', null ),
				'bank_code' => $wgRequest->getText( 'bank_code', null ),
				'branch_code' => $wgRequest->getText( 'branch_code', null ),
				'country_code_bank' => $wgRequest->getText( 'country_code_bank', null ),
				'date_collect' => $wgRequest->getText( 'date_collect', null ),
				'direct_debit_text' => $wgRequest->getText( 'direct_debit_text', null ),
				'iban' => $wgRequest->getText( 'iban', null ),
				'fiscal_number' => $wgRequest->getText( 'fiscal_number', null ),
				'transaction_type' => $wgRequest->getText( 'transaction_type', null ),
				'form_name' => $wgRequest->getText( 'form_name', null ),
				'ffname' => $wgRequest->getText( 'ffname', null ),
				'recurring' => $wgRequest->getVal( 'recurring', null ), //boolean type
				'recurring_paypal' => $wgRequest->getVal( 'recurring_paypal', null ), //boolean type, legacy key
				'user_ip' => null, //placeholder. We'll make these in a minute.
				'server_ip' => null,
			);
			if ( !$this->wasPosted() ) {
				$this->setVal( 'posted', false );
			}
		}
		
		//if we have saved any donation data to the session, pull them in as well.
		$this->integrateDataFromSession();

		$this->doCacheStuff();

		$this->normalize();

	}
	
	/**
	 * populateData helper function 
	 * If donor session data has been set, pull the fields in the session that 
	 * are populated, and merge that with the data set we already have. 
	 */
	protected function integrateDataFromSession() {
		/** if the thing coming in from the session isn't already something,
		 * replace it.
		 * if it is: assume that the session data was meant to be replaced
		 * with better data.
		 * ...unless it's an explicit $overwrite * */
		$c = $this->getAdapterClass();
		if ( $c::session_exists() && array_key_exists( 'Donor', $_SESSION ) ) {
			//fields that should always overwrite with their original values
			$overwrite = array ( 'referrer' );
			foreach ( $_SESSION['Donor'] as $key => $val ) {
				if ( !$this->isSomething( $key ) ){
					$this->setVal( $key, $val );
				} else {
					if ( in_array( $key, $overwrite ) ) {
						$this->setVal( $key, $val );
					}
				}
			}
		}
	}

	/**
	 * Returns an array of normalized and escaped donation data
	 * @return array
	 */
	public function getDataEscaped() {
		$escaped = $this->normalized;
		array_walk( $escaped, array( $this, 'sanitizeInput' ) );
		return $escaped;
	}

	/**
	 * Returns an array of normalized (but unescaped) donation data
	 * @return array 
	 */
	public function getDataUnescaped() {
		return $this->normalized;
	}

	/**
	 * populateData helper function.
	 * If there is no external data provided upon DonationData construct, and
	 * the object was instantiated in test mode, populateData_Test in intended
	 * to provide a baseline minimum of data with which to run tests without
	 * exploding.
	 * Populates $this->normalized.
	 * TODO: Implement an override for the test data, in the event that a
	 * partial data array is provided when DonationData is instantiated.
	 * @param array|bool $testdata Intended to implement an override for any values
	 * that may be provided on instantiation.
	 */
	protected function populateData_Test( $testdata = false ) {
		// define arrays of cc's and cc #s for random selection
		$cards = array( 'american' );
		$card_nums = array(
			'american' => array(
				378282246310005
			),
		);

		// randomly select a credit card
		$card_index = array_rand( $cards );

		// randomly select a credit card #
		$card_num_index = array_rand( $card_nums[$cards[$card_index]] );

		//This array should be populated with general test defaults, or 
		//(preferably)  mappings to random stuff... if we keep this around at all.
		//Certainly nothing pulled from a form post or get. 
		$this->normalized = array(
			'amount' => "35",
			'amountOther' => '',
			'email' => 'test@example.com',
			'fname' => 'Tester',
			'mname' => 'T.',
			'lname' => 'Testington',
			'street' => '548 Market St.',
			'street_supplemental' => '3rd floor',
			'city' => 'San Francisco',
			'state' => 'CA',
			'zip' => '94104',
			'country' => 'US',
			'fname2' => 'Testy',
			'lname2' => 'Testerson',
			'street2' => '123 Telegraph Ave.',
			'city2' => 'Berkeley',
			'state2' => 'CA',
			'zip2' => '94703',
			'country2' => 'US',
			'size' => 'small',
			'premium_language' => 'es',
			'card_num' => $card_nums[$cards[$card_index]][$card_num_index],
			'card_type' => $cards[$card_index],
			'expiration' => date( 'my', strtotime( '+1 year 1 month' ) ),
			'cvv' => '001',
			'currency_code' => 'USD',
			'payment_method' => 'cc',
			'payment_submethod' => '', //cards have no payment submethods. 
			'issuer_id' => '',
			'order_id' => '1234567890',
			'i_order_id' => '1234567890',
			'referrer' => 'http://www.baz.test.com/index.php?action=foo&action=bar',
			'utm_source' => 'test_src',
			'utm_source_id' => null,
			'utm_medium' => 'test_medium',
			'utm_campaign' => 'test_campaign',
			'language' => 'en',
			'comment' => 0,
			'token' => '',
			'contribution_tracking_id' => '',
			'data_hash' => '',
			'action' => '',
			'gateway' => 'payflowpro',
			'owa_session' => '',
			'owa_ref' => 'http://localhost/defaultTestData',
			'user_ip' => '12.12.12.12',
		);
	}

	/**
	 * Tells you if a value in $this->normalized is something or not. 
	 * @param string $key The field you would like to determine if it exists in 
	 * a usable way or not. 
	 * @return boolean true if the field is something. False if it is null, or 
	 * an empty string. 
	 */
	public function isSomething( $key ) {
		if ( array_key_exists( $key, $this->normalized ) ) {
			if ( is_null($this->normalized[$key]) || $this->normalized[$key] === '' ) {
				return false;
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 * getVal_Escaped
	 * @param string $key The data field you would like to retrieve. Pulls the 
	 * data from $this->normalized if it is found to be something. 
	 * @return mixed The normalized and escaped value of that $key. 
	 */
	public function getVal_Escaped( $key ) {
		if ( $this->isSomething( $key ) ) {
			//TODO: If we ever start sanitizing in a more complicated way, we should move this 
			//off to a function and have both getVal_Escaped and sanitizeInput call that. 
			return htmlspecialchars( $this->normalized[$key], ENT_COMPAT, 'UTF-8', false );
		} else {
			return null;
		}
	}
	
	/**
	 * getVal
	 * For Internal Use Only! External objects should use getVal_Escaped.
	 * @param string $key The data field you would like to retrieve directly 
	 * from $this->normalized. 
	 * @return mixed The normalized value of that $key. 
	 */
	protected function getVal( $key ) {
		if ( $this->isSomething( $key ) ) {
			return $this->normalized[$key];
		} else {
			return null;
		}
	}

	/**
	 * Sets a key in the normalized data array, to a new value.
	 * This function should only ever be used for keys that are not listed in 
	 * DonationData::getCalculatedFields().
	 * TODO: If the $key is listed in DonationData::getCalculatedFields(), use 
	 * DonationData::addData() instead. Or be a jerk about it and throw an 
	 * exception. (Personally I like the second one)
	 * @param string $key The key you want to set.
	 * @param string $val The value you'd like to assign to the key. 
	 */
	public function setVal( $key, $val ) {
		$this->normalized[$key] = $val;
	}

	/**
	 * Removes a value from $this->normalized. 
	 * @param string $key type
	 */
	public function expunge( $key ) {
		if ( array_key_exists( $key, $this->normalized ) ) {
			unset( $this->normalized[$key] );
		}
	}
	
	/**
	 * Returns an array of all the fields that get re-calculated during a 
	 * normalize. 
	 * This can be used on the outside when in the process of changing data, 
	 * particularly if any of the recalculted fields need to be restaged by the 
	 * gateway adapter. 
	 * @return array An array of values matching all recauculated fields.  
	 */
	public function getCalculatedFields() {
		$fields = array(
			'utm_source',
			'amount',
			'order_id',
			'i_order_id',
			'gateway',
			'optout',
			'anonymous',
			'language',
			'premium_language',
			'contribution_tracking_id', //sort of...
			'currency_code',
			'user_ip',
		);
		return $fields;
	}

	/**
	 * Normalizes the current set of data, just after it's been 
	 * pulled (or re-pulled) from a data source. 
	 * Care should be taken in the normalize helper functions to write code in 
	 * such a way that running them multiple times on the same array won't cause 
	 * the data to stroll off into the sunset: Normalize will definitely need to 
	 * be called multiple times against the same array.
	 */
	protected function normalize() {
		if ( !empty( $this->normalized ) ) {
			$this->setNormalizedOrderIDs();
			$this->setIPAddresses();
			$this->setNormalizedRecurring();
			$this->setNormalizedPaymentMethod(); //need to do this before utm_source.
			$this->setUtmSource();
			$this->setNormalizedAmount();
			$this->setGateway();
			$this->setLanguage();
			$this->setCountry(); //must do this AFTER setIPAddress...
			$this->handleContributionTrackingID();
			$this->setCurrencyCode(); // AFTER setCountry
			$this->renameCardType();
			$this->setEmail();
			
			$this->getValidationErrors();
		}
	}
	
	/**
	 * normalize helper function
	 * Sets user_ip and server_ip. 
	 */
	protected function setIPAddresses(){
		global $wgRequest;
		//if we are coming in from the orphan slayer, the client ip should 
		//already be populated with something un-local, and we'd want to keep 
		//that.
		if ( !$this->isSomething( 'user_ip' ) || $this->getVal( 'user_ip' ) === '127.0.0.1' ){
			if ( isset($wgRequest) ){
				$this->setVal( 'user_ip', $wgRequest->getIP() );
			}
		}
		
		if ( array_key_exists( 'SERVER_ADDR', $_SERVER ) ){
			$this->setVal( 'server_ip', $_SERVER['SERVER_ADDR'] );
		} else {
			//command line? 
			$this->setVal( 'server_ip', '127.0.0.1' );
		}
		
		
	}
	
	/**
	 * munge the legacy card_type field into payment_submethod
	 */
	protected function renameCardType()
	{
		if ($this->getVal('payment_method') == 'cc')
		{
			if ($this->isSomething('card_type'))
			{
				$this->setVal('payment_submethod', $this->getVal('card_type'));
			}
		}
	}
	
	/**
	 * normalize helper function
	 * Setting the country correctly. Country is... kinda important.
	 * If we have no country, or nonsense, we try to get something rational
	 * through GeoIP lookup.
	 */
	protected function setCountry() {
		$regen = true;
		$country = '';

		if ( $this->isSomething( 'country' ) ) {
			$country = strtoupper( $this->getVal( 'country' ) );
			if ( DataValidator::is_valid_iso_country_code( $country ) ) {
				$regen = false;
			} else {
				//check to see if it's one of those other codes that comes out of CN, for the logs
				//If this logs annoying quantities of nothing useful, go ahead and kill this whole else block later.
				//we're still going to try to regen.
				$near_countries = array ( 'XX', 'EU', 'AP', 'A1', 'A2', 'O1' );
				if ( !in_array( $country, $near_countries ) ) {
					$this->log( $this->getLogMessagePrefix() . __FUNCTION__ . ": $country is not a country, or a recognized placeholder.", LOG_WARNING );
				}
			}
		} else {
			$this->log( $this->getLogMessagePrefix() . __FUNCTION__ . ': Country not set.', LOG_WARNING );
		}

		//try to regenerate the country if we still don't have a valid one yet
		if ( $regen ) {
			// If no valid country was passed, try to do GeoIP lookup
			// Requires php5-geoip package
			if ( function_exists( 'geoip_country_code_by_name' ) ) {
				$ip = $this->getVal( 'user_ip' );
				if ( IP::isValid( $ip ) ) {
					//I hate @suppression at least as much as you do, but this geoip function is being genuinely horrible.
					//try/catch did not help me suppress the notice it emits when it can't find a host.
					//The goggles; They do *nothing*.
					$country = @geoip_country_code_by_name( $ip );
					if ( !$country ) {
						$this->log( $this->getLogMessagePrefix() . __FUNCTION__ . ": GeoIP lookup function found nothing for $ip! No country available.", LOG_WARNING );
					}
				}
			} else {
				$this->log( $this->getLogMessagePrefix() . 'GeoIP lookup function is missing! No country available.', LOG_WARNING );
			}

			//still nothing good? Give up.
			if ( !DataValidator::is_valid_iso_country_code( $country ) ) {
				$country = 'XX';
			}
		}

		if ( $country != $this->getVal( 'country' ) ) {
			$this->setVal( 'country', $country );
		}
	}
	
	/**
	 * normalize helper function
	 * Setting the currency code correctly. 
	 * Historically, this value could come in through 'currency' or 
	 * 'currency_code'. After this fires, we will only have 'currency_code'. 
	 */
	protected function setCurrencyCode() {
		//at this point, we can have either currency, or currency_code.
		//-->>currency_code has the authority!<<-- 
		$currency = false;
		
		if ( $this->isSomething( 'currency' ) ) {
			$currency = $this->getVal( 'currency' );
			$this->expunge( 'currency' );
			$this->log( $this->getLogMessagePrefix() . "Got currency from 'currency', now: $currency", LOG_DEBUG );
		}
		if ( $this->isSomething( 'currency_code' ) ) {
			$currency = $this->getVal( 'currency_code' );
			$this->log( $this->getLogMessagePrefix() . "Got currency from 'currency_code', now: $currency", LOG_DEBUG );
		}
		
		//TODO: This is going to fail miserably if there's no country yet.
		if ( !$currency ){
			require_once( dirname( __FILE__ ) . '/nationalCurrencies.inc' );
			$currency = getNationalCurrency($this->getVal('country'));
			$this->log( $this->getLogMessagePrefix() . "Got currency from 'country', now: $currency", LOG_DEBUG );
		}
		
		$this->setVal( 'currency_code', $currency );
	}
	
	/**
	 * normalize helper function.
	 * Assures that if no contribution_tracking_id is present, a row is created 
	 * in the Contribution tracking table, and that row is assigned to the 
	 * current contribution we're tracking. 
	 * If a contribution tracking id is already present, no new rows will be 
	 * assigned. 
	 */
	protected function handleContributionTrackingID(){
		if ( !$this->isSomething( 'contribution_tracking_id' ) ) {
			if ( !$this->isCaching() ) {
				$this->saveContributionTracking();
			} else {
				$this->log( $this->getLogMessagePrefix() . "Declining to create a contribution_tracking record, because we are in cache mode." );
			}
		}
	}
	
	/**
	 * Tells us if we think we're in caching mode or not. 
	 * @staticvar string $cache Keeps track of the mode so we don't have to 
	 * calculate it from the data fields more than once. 
	 * @return boolean true if we are going to be caching, false if we aren't. 
	 */
	public function isCaching(){
		
		static $cache = null;

		if ( is_null( $cache ) ){
			if ( $this->getVal( '_cache_' ) === 'true' ){ //::head. hit. keyboard.::
				if ( $this->isSomething( 'utm_source_id' ) && !is_null( 'utm_source_id' ) ){
					$cache = true;
				}
			}
			if ( is_null( $cache ) ){
				$cache = false;
			}
		}
		
		 //this business could change at any second, and it will prevent us from 
		 //caching, so we're going to keep asking if it's set.
		$c = $this->getAdapterClass();
		if ( $c::session_exists() ) {
			$cache = false;
		}		
		
		return $cache;
	}
	
	/**
	 * normalize helper function.
	 * Takes all possible sources for the intended donation amount, and 
	 * normalizes them into the 'amount' field.  
	 */
	protected function setNormalizedAmount() {
		if ( $this->getVal( 'amount' ) === 'Other' ){
			$this->setVal( 'amount', $this->getVal( 'amountGiven' ) );
		}

		$amountIsNotValidSomehow = ( !( $this->isSomething( 'amount' )) ||
			!is_numeric( $this->getVal( 'amount' ) ) ||
			$this->getVal( 'amount' ) <= 0 );

		if ( $amountIsNotValidSomehow &&
			( $this->isSomething( 'amountGiven' ) && is_numeric( $this->getVal( 'amountGiven' ) ) ) ) {
			$this->setVal( 'amount', $this->getVal( 'amountGiven' ) );
		} else if ( $amountIsNotValidSomehow &&
			( $this->isSomething( 'amountOther' ) && is_numeric( $this->getVal( 'amountOther' ) ) ) ) {
			$this->setVal( 'amount', $this->getVal( 'amountOther' ) );
		}
		
		if ( !($this->isSomething( 'amount' )) ){
			$this->setVal( 'amount', '0.00' );
		}
		
		$this->expunge( 'amountGiven' );
		$this->expunge( 'amountOther' );

		if ( !is_numeric( $this->getVal( 'amount' ) ) ){
			//fail validation later, log some things.
			$mess = $this->getLogMessagePrefix() . 'Non-numeric Amount.';
			$keys = array(
				'amount',
				'utm_source',
				'utm_campaign',
				'email',
				'user_ip', //to help deal with fraudulent traffic.
			);
			foreach ( $keys as $key ){
				$mess .= ' ' . $key . '=' . $this->getVal( $key );
			}
			$this->log( $mess, LOG_DEBUG );
			$this->setVal('amount', 'invalid');
			return;
		}
		
		if ( DataValidator::is_fractional_currency( $this->getVal( 'currency_code' ) ) ){
			$this->setVal( 'amount', number_format( $this->getVal( 'amount' ), 2, '.', '' ) );
		} else {
			$this->setVal( 'amount', floor( $this->getVal( 'amount' ) ) );
		}
	}

	/**
	 * normalize helper function.
	 * Takes all possible names for recurring and normalizes them into the 'recurring' field.
	 */
	protected function setNormalizedRecurring() {
		if ( $this->isSomething( 'recurring_paypal' ) && ( $this->getVal( 'recurring_paypal' ) === '1' || $this->getVal( 'recurring_paypal' ) === 'true' ) ) {
			$this->setVal( 'recurring', true );
			$this->expunge('recurring_paypal');
		}
		if ( $this->isSomething( 'recurring' ) && ( $this->getVal( 'recurring' ) === '1' || $this->getVal( 'recurring' ) === 'true' ) ) {
			$this->setVal( 'recurring', true );
		}
		else{
			$this->setVal( 'recurring', false );
		}
	}

	/**
	 * normalize helper function.
	 * Ensures that order_id and i_order_id are ready to go, depending on what 
	 * comes in populated or not, and where it came from.
	 *
	 * @return null
	 */
	protected function setNormalizedOrderIDs( ) {
		static $idGenThisRequest = false;
		$id = null;

		// We need to obtain and set the order_id every time this function is called. If there's
		// one already in existence (ie: in the GET string) we will use that one.
		if ( array_key_exists( 'order_id', $_GET ) ) {
			// This must come only from the get string. It's there to process return calls.
			// TODO: Move this somewhere more sane! We shouldn't be doing anything with requests
			// in normalization functions.
			$id = $_GET['order_id'];
		} elseif ( $this->getAdapterClass() == 'AdyenAdapter' && array_key_exists( 'merchantReference', $_GET ) ) {
			$id = $_GET['merchantReference'];
		} elseif ( ( $this->isSomething( 'order_id' ) ) && ( $idGenThisRequest == true ) ){
			// An order ID already exists, therefore we do nothing
			$id = $this->getVal( 'order_id' );
		} else {
			// Apparently we need a new one
			$idGenThisRequest = true;
			$id = $this->generateOrderId();
		}

		// HACK: We used to have i_order_id remain consistent; but that might confuse things,
		// so now it just follows order_id; and we only keep it for legacy reasons (ie: I have
		// no idea what it would do if I removed it.)

		$this->setVal( 'order_id', $id );
		$this->setVal( 'i_order_id', $id );
	}

	/**
	 * normalize helper function.
	 * Collapses the various versions of payment method and submethod.
	 *
	 * @return null
	 */
	protected function setNormalizedPaymentMethod() {
		$method = '';
		$submethod = '';
		// payment_method and payment_submethod are currently preferred within DonationInterface
		if ( $this->isSomething( 'payment_method' ) ) {
			$method = $this->getVal( 'payment_method' );

			//but they can come in a little funny.
			$exploded = explode( '.', $method );
			if ( count( $exploded ) > 1 ) {
				$method = $exploded[0];
				$submethod = $exploded[1];
			}
		}

		if ( $this->isSomething( 'payment_submethod' ) ) {
			if ( $submethod != '' ) {
				//squak a little if they don't match, and pick one.
				if ( $submethod != $this->getVal( 'payment_submethod' ) ) {
					$message = $this->getLogMessagePrefix() . "Submethod normalization conflict!: ";
					$message .= 'payment_submethod = ' . $this->getVal( 'payment_submethod' );
					$message .= ", and exploded payment_method = '$submethod'. Going with the first option.";
					$this->log( $message, LOG_DEBUG );
				}
			}
			$submethod = $this->getVal( 'payment_submethod' );
		}

		if ( $this->isSomething( 'paymentmethod' ) ) { //gross. Why did we do this?
			//...okay. So, if we have this value, we've likely just come in from the form chooser,
			//which has just used *this* value to choose a form with.
			//so, go ahead and prefer this version, and then immediately nuke it.
			$method = $this->getVal( 'paymentmethod' );
			$this->expunge( 'paymentmethod' );
		}

		if ( $this->isSomething( 'submethod' ) ) { //same deal
			$submethod = $this->getVal( 'submethod' );
			$this->expunge( 'submethod' );
		}

		$this->setVal( 'payment_method', $method );
		$this->setVal( 'payment_submethod', $submethod );
	}

	/**
	 * Generate an order id
	 *
	 * @return A randomized order ID
	 */
	protected static function generateOrderId() {
		//$order_id = ( double ) microtime() * 1000000 . mt_rand( 1000, 9999 );
		$order_id = (string) mt_rand( 1000, 9999999999 );
		return $order_id;
	}

	/**
	 * Sanitize user input.
	 *
	 * Intended to be used with something like array_walk.
	 *
	 * @param $value string The value of the array
	 * @param $key string The key of the array
	 * @param $flags int The flag constant for htmlspecialchars
	 * @param $double_encode bool Whether or not to double-encode strings
	 */
	protected function sanitizeInput( &$value, $key, $flags=ENT_COMPAT, $double_encode=false ) {
		$value = htmlspecialchars( $value, $flags, 'UTF-8', $double_encode );
	}

	/**
	 * log: This grabs the adapter class that instantiated DonationData, and
	 * uses its log function.
	 * @param string $message The message to log.
	 * @param int|string $log_level
	 */
	protected function log( $message, $log_level=LOG_INFO ) {
		$c = $this->getAdapterClass();
		if ( $c && is_callable( array( $c, 'log' ) )){
			$c::log( $message, $log_level );
		}
	}

	/**
	 * getGatewayIdentifier
	 * This grabs the adapter class that instantiated DonationData, and returns 
	 * the result of its 'getIdentifier' function. Used for normalizing the 
	 * 'gateway' value, and stashing and retrieving the edit token (and other 
	 * things, where needed) in the session. 
	 * @return type 
	 */
	protected function getGatewayIdentifier() {
		$c = $this->getAdapterClass();
		if ( $c && is_callable( array( $c, 'getIdentifier' ) ) ){
			return $c::getIdentifier();
		} else {
			return 'DonationData';
		}
	}

	/**
	 * getGatewayGlobal
	 * This grabs the adapter class that instantiated DonationData, and returns 
	 * the result of its 'getGlobal' function for the $varname passed in. Used 
	 * to determine gateway-specific configuration settings. 
	 * @param string $varname the global variable (minus prefix) that we want to 
	 * check. 
	 * @return mixed  The value of the gateway global if it exists. Else, the 
	 * value of the Donation Interface global if it exists. Else, null.
	 */
	protected function getGatewayGlobal( $varname ) {
		$c = $this->getAdapterClass();
		if ( $c && is_callable( array( $c, 'getGlobal' ) ) ){
			return $c::getGlobal( $varname );
		} else {
			return false;
		}
	}

	/**
	 * normalize helper function.
	 * Sets the gateway to be the gateway that called this class in the first 
	 * place.
	 */
	protected function setGateway() {
		//TODO: Hum. If we have some other gateway in the form data, should we go crazy here? (Probably)
		$gateway = $this->gatewayID;
		$this->setVal( 'gateway', $gateway );
	}
	
	/**
	 * normalize helper function.
	 * If the language has not yet been set or is not valid, pulls the language code 
	 * from the current global language object. 
	 * Also sets the premium_language as the calculated language if it's not 
	 * already set coming in (had been defaulting to english). 
	 */
	protected function setLanguage() {
		global $wgLang;
		$language = false;
		
		if ( $this->isSomething( 'uselang' ) ) {
			$language = $this->getVal( 'uselang' );
		} elseif ( $this->isSomething( 'language' ) ) {
			$language = $this->getVal( 'language' );
		}
		
		if ( $language == false
			|| !Language::isValidBuiltInCode( $this->normalized['language'] ) )
		{
			$language = $wgLang->getCode() ;
		}
		
		$this->setVal( 'language', $language );
		$this->expunge( 'uselang' );
		
		if ( !$this->isSomething( 'premium_language' ) ){
			$this->setVal( 'premium_language', $language );
		}
		
	}

	/**
	 * Normalize email to 'nobody' if nothing has been entered.
	 */
	protected function setEmail() {
		if ( !$this->isSomething( 'email' ) ) {
			$this->setVal( 'email', 'nobody@wikimedia.org' );
		}
	}

	/**
	 * This function sets the token to the string 'cache' if we're caching, and 
	 * then sets the s-maxage header to whatever you specify for the SMaxAge.
	 * NOTES: The bit where we setSquidMaxage will not work at all, under two 
	 * conditions: 
	 * The user has a session ID.
	 * The mediawiki_session cookie is set in the user's browser.
	 * @global bool $wgUseSquid
	 * @global type $wgOut 
	 */
	protected function doCacheStuff() {
		//TODO: Wow, name.
		// if _cache_ is requested by the user, do not set a session/token; dynamic data will be loaded via ajax
		if ( $this->isCaching() ) {
			self::log( $this->getAnnoyingOrderIDLogLinePrefix() . ' Cache requested', LOG_DEBUG );
			$this->setVal( 'token', 'cache' );

			// if we have squid caching enabled, set the maxage
			global $wgUseSquid, $wgOut;
			$maxAge = $this->getGatewayGlobal( 'SMaxAge' );
			
			if ( $wgUseSquid && ( $maxAge !== false ) ) {
				self::log( $this->getAnnoyingOrderIDLogLinePrefix() . ' Setting s-max-age: ' . $maxAge, LOG_DEBUG );
				$wgOut->setSquidMaxage( $maxAge );
			}
		}
	}

	/**
	 * getAnnoyingOrderIDLogLinePrefix
	 * Constructs and returns the annoying order ID log line prefix. 
	 * This has moved from being annoyingly all over the place in the edit token 
	 * logging code before it was functionalized, to being annoying to look at 
	 * in the logs because the two numbers in the prefix are frequently 
	 * identical (and large).
	 * TODO: Determine if anything actually looks at both of those numbers, in 
	 * order to make this less annoying. Rename on success. 
	 * @return string Annoying Order ID Log Line Prefix in all its dubious glory. 
	 */
	protected function getAnnoyingOrderIDLogLinePrefix() {
		return $this->getVal( 'order_id' ) . ' ' . $this->getVal( 'i_order_id' ) . ': ';
	}

	/**
	 * getLogMessagePrefix
	 * Constructs and returns the standard ctid:order_id log line prefix. 
	 * This should eat getAnnoyingOrderIDLogLinePrefix() everywhere, as soon as
	 * we can audit all our external log parsing scripts to make sure we're not
	 * going to break anything. 
	 * @return string "ctid:order_id: " 
	 */
	protected function getLogMessagePrefix() {
		return $this->getVal( 'contribution_tracking_id' ) . ' ' . $this->getVal( 'order_id' ) . ': ';
	}

	/**
	 * normalize helper function.
	 * 
	 * the utm_source is structured as: banner.landing_page.payment_method_family
	 */
	protected function setUtmSource() {
		
		$utm_source = $this->getVal( 'utm_source' );
		$utm_source_id = $this->getVal( 'utm_source_id' );
		
		$payment_method_family = PaymentMethod::getUtmSourceName(
			$this->getVal( 'payment_method' ),
			$this->getVal( 'recurring' )
		);

		$this->log( $this->getLogMessagePrefix() . "Setting utm_source payment method to {$payment_method_family}", LOG_INFO );

		// split the utm_source into its parts for easier manipulation
		$source_parts = explode( ".", $utm_source );

		// If we don't have the banner or any utm_source, set it to the empty string.
		if ( empty( $source_parts[0] ) ) {
			$source_parts[0] = '';
		}

		// If the utm_source_id is set, include that in the landing page
		// portion of the string.
		if ( $utm_source_id ) {
			$source_parts[1] = $payment_method_family . $utm_source_id;
		} else {
			if ( empty( $source_parts[1] ) ) {
				$source_parts[1] = '';
			}
		}

		$source_parts[2] = $payment_method_family;
		if ( empty( $source_parts[2] ) ) {
			$source_parts[2] = '';
		}

		// reconstruct, and set the value.
		$utm_source = implode( ".", $source_parts );
		$this->setVal( 'utm_source' , $utm_source );
	}

	/**
	 * Clean array of tracking data to contain valid fields
	 *
	 * Compares tracking data array to list of valid tracking fields and
	 * removes any extra tracking fields/data.  Also sets empty values to
	 * 'null' values.
	 * @param bool $unset If set to true, empty values will be unset from the 
	 * return array, rather than set to null. (default: false)
	 * @return array Clean tracking data 
	 */
	public function getCleanTrackingData( $unset = false ) {
		global $wgContributionTrackingAnalyticsUpgrade;

		// define valid tracking fields
		$tracking_fields = array(
			'note',
			'referrer',
			'anonymous',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'utm_key',
			'optout',
			'language',
			'country',
			'ts'
		);

		$tracking_data = array();

		foreach ( $tracking_fields as $value ) {
			if ( $this->isSomething( $value ) ) {
				$tracking_data[$value] = $this->getVal( $value );
			} else {
				if ( !$unset ){
					$tracking_data[$value] = null;
				}
			}
		}

		if( $this->isSomething( 'currency_code' ) && $this->isSomething( 'amount' ) ){
			$tracking_data['form_amount'] = $this->getVal( 'currency_code' ) . " " . $this->getVal( 'amount' );
		}
		if( $this->isSomething( 'form_name' ) ){
			$tracking_data['payments_form'] = $this->getVal( 'form_name' );
			if( $this->isSomething( 'ffname' ) ){
				$tracking_data['payments_form'] .= '.' . $this->getVal( 'ffname' );
			}
		}

		return $tracking_data;
	}

	/**
	 * Saves a NEW ROW in the Contribution Tracking table and returns the new ID. 
	 * @return boolean true if we got a contribution tracking # back, false if 
	 * something went wrong.  
	 */
	public function saveContributionTracking() {

		$tracked_contribution = $this->getCleanTrackingData();

		// insert tracking data and get the tracking id
		$result = $this->insertContributionTracking( $tracked_contribution );

		$this->setVal( 'contribution_tracking_id', $result );

		if ( !$result ) {
			return false;
		}
		return true;
	}

	/**
	 * Insert a record into the contribution_tracking table
	 *
	 * @param array $tracking_data The array of tracking data to insert to contribution_tracking
	 * @return mixed Contribution tracking ID or false on failure
	 */
	public function insertContributionTracking( $tracking_data ) {
		$db = ContributionTrackingProcessor::contributionTrackingConnection();

		// FIXME: impossible condition.
		if ( !$db ) {
			return false;
		}

		// set the time stamp if it's not already set
		if ( !isset( $tracking_data['ts'] ) || !strlen( $tracking_data['ts'] ) ) {
			$tracking_data['ts'] = $db->timestamp();
		}

		// Store the contribution data
		if ( $db->insert( 'contribution_tracking', $tracking_data ) ) {
			return $db->insertId();
		} else {
			$this->log( $this->getLogMessagePrefix() . 'Failed to create a new contribution_tracking record', LOG_ERR );
			return false;
		}
	}

	/**
	 * Update contribution_tracking table
	 *
	 * @param bool $force If set to true, will ensure that contribution tracking is updated
	 */
	public function updateContributionTracking( $force = false ) {
		// ony update contrib tracking if we're coming from a single-step landing page
		// which we know with cc# in utm_source or if force=true or if contribution_tracking_id is not set
		if ( !$force &&
			!preg_match( "/cc[0-9]/", $this->getVal( 'utm_source' ) ) &&
			is_numeric( $this->getVal( 'contribution_tracking_id' ) ) ) {
			return;
		}

		$db = ContributionTrackingProcessor::contributionTrackingConnection();

		// if contrib tracking id is not already set, we need to insert the data, otherwise update
		if ( !$this->getVal( 'contribution_tracking_id' ) ) {
			$tracked_contribution = $this->getCleanTrackingData();
			$this->setVal( 'contribution_tracking_id', $this->insertContributionTracking( $tracked_contribution ) );
		} else {
			$tracked_contribution = $this->getCleanTrackingData( true );
			$db->update( 'contribution_tracking', $tracked_contribution, array( 'id' => $this->getVal( 'contribution_tracking_id' ) ) );
		}
	}

	/**
	 * Adds an array of data to the normalized array, and then re-normalizes it. 
	 * NOTE: If any gateway is using this function, it should then immediately 
	 * repopulate its own data set with the DonationData source, and then 
	 * re-stage values as necessary.
	 *
	 * @param array $newdata An array of data to integrate with the existing 
	 * data held by the DonationData object.
	 */
	public function addData( $newdata ) {
		if ( is_array( $newdata ) && !empty( $newdata ) ) {
			foreach ( $newdata as $key => $val ) {
				if ( !is_array( $val ) ) {
					$this->setVal( $key, $val );
				}
			}
		}
		$this->normalize();
	}

	/**
	 * Gets the name of the adapter class that instantiated DonationData. 
	 * @return mixed The name of the class if it exists, or false. 
	 */
	protected function getAdapterClass(){
		return get_class( $this->gateway );
	}
	
	/**
	 * Returns an array of field names we intend to send to activeMQ via a Stomp 
	 * message. Note: These are field names from the FORM... not the field names 
	 * that will appear in the stomp message. 
	 * TODO: Move the mapping for donation data from 
	 * /extensions/DonationData/activemq_stomp/activemq_stomp.php
	 * to somewhere in DonationData. 	 * 
	 */
	public static function getStompMessageFields() {
		$stomp_fields = array(
			'contribution_tracking_id',
			'optout',
			'anonymous',
			'comment',
			'size',
			'premium_language',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'language',
			'referrer',
			'email',
			'fname',
			'mname',
			'lname',
			'street',
			'street_supplemental',
			'city',
			'state',
			'country',
			'zip',
			'fname2',
			'lname2',
			'street2',
			'city2',
			'state2',
			'country2',
			'zip2',
			'gateway',
			'gateway_account',
			'gateway_txn_id',
			'recurring',
			'payment_method',
			'payment_submethod',
			'response',
			'currency_code',
			'amount',
			'user_ip',
			'date',
		);
		return $stomp_fields;
	}

	/**
	 * Returns an array of field names we need in order to retry a payment
	 * after the session has been destroyed by... overzealousness.
	 */
	public static function getRetryFields() {
		$fields = array (
			'gateway',
			'country',
			'currency_code',
			'amount',
			'language',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'payment_method',
		);
		return $fields;
	}

	/**
	 * Basically, this is a wrapper for the $wgRequest wasPosted function that 
	 * won't give us notices if we weren't even a web request. 
	 * I realize this is pretty lame. 
	 * Notices, however, are more lame. 
	 * @staticvar string $posted Keeps track so we don't have to figure it out twice.
	 */
	public function wasPosted(){
		global $wgRequest;
		static $posted = null;
		if ($posted === null){
			$posted = (array_key_exists('REQUEST_METHOD', $_SERVER) && $wgRequest->wasPosted());
		}
		return $posted; 
	}
	
	/**
	 * getValidationErrors
	 * This function will go through all the data we have pulled from wherever 
	 * we've pulled it, and make sure it's safe and expected and everything. 
	 * If it is not, it will return an array of errors ready for any 
	 * DonationInterface form class derivitive to display. 
	 */
	public function getValidationErrors( $recalculate = false, $check_not_empty = array() ){
		if ( is_null( $this->validationErrors ) || $recalculate ) {
			$this->validationErrors = DataValidator::validate( $this->gateway, $this->normalized, $check_not_empty );
		}
		return $this->validationErrors;
	}
	
	/**
	 * validatedOK
	 * Checks to see if the data validated ok (no errors). 
	 * @return boolean True if no errors, false if errors exist. 
	 */
	public function validatedOK() {
		if ( is_null( $this->validationErrors ) ){
			$this->getValidationErrors();
		}
		
		if ( count( $this->validationErrors ) === 0 ){
			return true;
		}
		return false;
	}

	/**
	 * Resets the order ID and re-normalizes the data set. This effectively creates a new
	 * transaction.
	 */
	public function resetOrderId() {
		$this->expunge( 'order_id' );
		$this->normalize();
	}

	/**
	 * Take data from the return get string; must be in the passed in var_map. After calling this
	 * function data will need to be restated.
	 *
	 * @param $var_map
	 */
	public function addVarMapDataFromURI( $var_map ) {
		global $wgRequest;

		// Obtain data parameters for STOMP message injection
		//n.b. these request vars were from the _previous_ api call
		$add_data = array();
		foreach ( $var_map as $gateway_key => $normal_key ) {
			$value = $wgRequest->getVal( $gateway_key, null );
			if ( !empty( $value ) ) {
				// Deal with some fun special cases
				switch ( $gateway_key ) {
					case 'transactionAmount':
						list ($currency, $amount) = explode( ' ', $value );
						$add_data[ 'currency' ] = $currency;
						$add_data[ 'amount' ] = $amount;
						break;

					case 'buyerName':
						list ($fname, $lname) = explode( ' ', $value, 2 );
						$add_data[ 'fname' ] = $fname;
						$add_data[ 'lname' ] = $lname;
						break;

					default:
						$add_data[ $normal_key ] = $value;
						break;
				}
			}
		}

		//TODO: consider prioritizing the session vars
		$this->addData( $add_data );
	}
}

?>
