<?php
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;

/**
 * Generic Donation API
 * This API should be able to accept donation submissions for any gateway or payment type
 * Call with api.php?action=donate
 */
class DonationApi extends ApiBase {
	public $donationData, $gateway;
	public function execute() {
		$this->donationData = $this->extractRequestParams();

		$this->gateway = $this->donationData['gateway'];

		DonationInterface::setSmashPigProvider( $this->gateway );
		$gatewayObj = $this->getGatewayObject();

		// FIXME: SmashPig should just use Monolog.
		Logger::getContext()->enterContext( $gatewayObj->getLogMessagePrefix() );

		if ( !$gatewayObj ) {
			return; // already failed with a dieUsage call
		}

		$validated_ok = $gatewayObj->validatedOK();
		if ( !$validated_ok ) {
			$errors = $gatewayObj->getErrorState()->getErrors();
			$outputResult['errors'] = self::serializeErrors( $errors, $gatewayObj );
			// FIXME: What is this junk?  Smaller API, like getResult()->addErrors
			$this->getResult()->setIndexedTagName( $outputResult['errors'], 'error' );
			$this->getResult()->addValue( null, 'result', $outputResult );
			return;
		}

		$paymentResult = $gatewayObj->doPayment();

		$outputResult = [
			'iframe' => $paymentResult->getIframe(),
			'redirect' => $paymentResult->getRedirect(),
			'formData' => $paymentResult->getFormData()
		];

		$errors = $paymentResult->getErrors();

		$sendingDonorToProcessor = empty( $errors ) &&
			( !empty( $outputResult['iframe'] ) || !empty( $outputResult['redirect'] ) );

		if ( $sendingDonorToProcessor ) {
			$gatewayObj->logPending();
			$this->markLiberatedOnRedirect( $paymentResult, $gatewayObj );
		}

		if ( !empty( $errors ) ) {
			$outputResult['errors'] = self::serializeErrors( $errors, $gatewayObj );
			$this->getResult()->setIndexedTagName( $outputResult['errors'], 'error' );
		}

		$this->getResult()->addValue( null, 'result', $outputResult );
	}

	public static function serializeErrors( $errors, GatewayAdapter $adapter ) {
		$serializedErrors = [];
		foreach ( $errors as $error ) {
			if ( $error instanceof ValidationError ) {
				$message = WmfFramework::formatMessage(
					$error->getMessageKey(),
					$error->getMessageParams()
				);
				$serializedErrors[$error->getField()] = $message;
			} elseif ( $error instanceof PaymentError ) {
				$message = $adapter->getErrorMapByCodeAndTranslate( $error->getErrorCode() );
				$serializedErrors['general'][] = $message;
			} else {
				$logger = DonationLoggerFactory::getLogger( $adapter );
				$logger->error( 'API trying to serialize unknown error type: ' . get_class( $error ) );
			}
		}
		return $serializedErrors;
	}

	public function isReadMode() {
		return false;
	}

	public function getAllowedParams() {
		return [
			'gateway' => $this->defineParam( true ),
			'contact_id' => $this->defineParam( false ),
			'contact_hash' => $this->defineParam( false ),
			'amount' => $this->defineParam( false ),
			'currency' => $this->defineParam( false ),
			'first_name' => $this->defineParam( false ),
			'last_name' => $this->defineParam( false ),
			'street_address' => $this->defineParam( false ),
			'supplemental_address_1' => $this->defineParam( false ),
			'city' => $this->defineParam( false ),
			'state_province' => $this->defineParam( false ),
			'postal_code' => $this->defineParam( false ),
			'email' => $this->defineParam( false ),
			'country' => $this->defineParam( false ),
			'card_num' => $this->defineParam( false ),
			'card_type' => $this->defineParam( false ),
			'expiration' => $this->defineParam( false ),
			'cvv' => $this->defineParam( false ),
			'payment_method' => $this->defineParam( false ),
			'payment_submethod' => $this->defineParam( false ),
			'processor_form' => $this->defineParam( false ),
			'language' => $this->defineParam( false ),
			'order_id' => $this->defineParam( false ),
			'wmf_token' => $this->defineParam( false ),
			'utm_source' => $this->defineParam( false ),
			'utm_campaign' => $this->defineParam( false ),
			'utm_medium' => $this->defineParam( false ),
			'referrer' => $this->defineParam( false ),
			'recurring' => $this->defineParam( false ),
			'variant' => $this->defineParam( false ),
			'opt_in' => $this->defineParam( false ),
		];
	}

	private function defineParam( $required = false, $type = 'string' ) {
		if ( $required ) {
			$param = [ ApiBase::PARAM_TYPE => $type, ApiBase::PARAM_REQUIRED => true ];
		} else {
			$param = [ ApiBase::PARAM_TYPE => $type ];
		}
		return $param;
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=donate&gateway=globalcollect&amount=2.00&currency=USD'
				=> 'apihelp-donate-example-1',
		];
	}

	/**
	 * @return GatewayAdapter
	 */
	protected function getGatewayObject() {
		$className = DonationInterface::getAdapterClassForGateway( $this->gateway );
		$variant = $this->getRequest()->getVal( 'variant' );
		return new $className( [ 'variant' => $variant ] );
	}

	/**
	 * If we are sending the donor to a payment processor with a full redirect
	 * rather than inside an iframe, mark the order ID as 'liberated' so when
	 * they come back, we don't waste time trying to pop them out of a frame.
	 *
	 * @param PaymentResult $paymentResult
	 * @param GatewayAdapter $adapter
	 */
	protected function markLiberatedOnRedirect(
		PaymentResult $paymentResult, GatewayAdapter $adapter
	) {
		if ( !$paymentResult->getRedirect() ) {
			return;
		}
		// Save a flag in session saying we don't need to pop out of an iframe
		// See related code in GatewayPage::handleResultRequest
		$oid = $adapter->getData_Unstaged_Escaped( 'order_id' );
		$sessionOrderStatus = $adapter->session_getData( 'order_status' );
		$sessionOrderStatus[$oid] = 'liberated';
		WmfFramework::setSessionValue( 'order_status', $sessionOrderStatus );
	}
}
