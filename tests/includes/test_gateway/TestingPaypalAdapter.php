<?php

/**
 * TestingPaypalAdapter
 * @TODO: Extend/damage things here. I'm sure we'll need it eventually...
 */
class TestingPaypalAdapter extends PaypalAdapter {
	public static $fakeGlobals = array();

	/**
	 * Set the error code you want the dummy response to return
	 */
	public function setDummyGatewayResponseCode( $code ) {
		$this->dummyGatewayResponseCode = $code;
	}

	/**
	 * Set the error code you want the dummy response to return
	 */
	public function setDummyCurlResponseCode( $code ) {
		$this->dummyCurlResponseCode = $code;
	}

	/**
	 * Load in some dummy response XML so we can test proper response processing
	 */
	protected function curl_exec( $ch ) {
		$code = '';
		if ( property_exists( $this, 'dummyGatewayResponseCode' ) ) {
			$code = '_' . $this->dummyGatewayResponseCode;
		}

		//could start stashing these in a further-down subdir if payment type starts getting in the way,
		//but frankly I don't want to write tests that test our dummy responses.
		$file_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
		$file_path .= 'Responses' . DIRECTORY_SEPARATOR . self::getIdentifier() . DIRECTORY_SEPARATOR;
		$file_path .= $this->getCurrentTransaction() . $code . '.testresponse';

		//these are all going to be short, so...
		if ( file_exists( $file_path ) ) {
			return file_get_contents( $file_path );
		} else {
			echo "File $file_path does not exist.\n"; //<-That will deliberately break the test.
			return false;
		}
	}

	/**
	 * Load in some dummy curl response info so we can test proper response processing
	 */
	protected function curl_getinfo( $ch, $opt = null ) {
		$code = 200; //response OK
		if ( property_exists( $this, 'dummyCurlResponseCode' ) ) {
			$code = ( int ) $this->dummyCurlResponseCode;
		}

		//put more here if it ever turns out that we care about it.
		return array (
			'http_code' => $code,
		);
	}

	public static function getGlobal( $name ) {
		if ( array_key_exists( $name, TestingPaypalAdapter::$fakeGlobals ) ) {
			return TestingPaypalAdapter::$fakeGlobals[$name];
		}
		return parent::getGlobal( $name );
	}
}
