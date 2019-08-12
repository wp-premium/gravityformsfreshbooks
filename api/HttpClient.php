<?php
/**
 * FreshBooks HttpClient Class
 *
 *
 * @package    FreshBooks

 * @copyright  Milan Rukavina, rukavinamilan@gmail.com
 * @version    1.0
 */


/**
 * Class Making Calls to the server
 */
class FreshBooks_HttpClient
{
	private $_url = "";
	private $_token = "";
	private $_curlConn = NULL;
/**
 * Singleton instance
 */
	protected static $_instance = null;

  /**
   * Constructor
   *
   * Instantiate using {@link getInstance()}; front controller is a singleton
   * object.
   *
   */
  protected function __construct()
  {
  	//
  }

	/**
	 * Enforce singleton; disallow cloning
	 */
	private function __clone()
	{
	}

	/**
	 * Singleton instance
	 *
	 * @return FreshBooks_HttpClient
	 */
	public static function getInstance()
	{
		if (null === self::$_instance) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

/**
 * init singleton object
 */
	public static function init($url,$token)
	{
		return self::getInstance()->_init($url,$token);
	}

/**
 * private init parameters: url and token
 */
	private function _init($url,$token)
	{
		$this->_url = $url;
		$this->_token = $token;
		//init connection
		$this->_curlConn = curl_init($this->_url);
		curl_setopt($this->_curlConn, CURLOPT_HEADER, false);
		curl_setopt($this->_curlConn, CURLOPT_NOBODY, false);
		curl_setopt($this->_curlConn, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->_curlConn, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($this->_curlConn, CURLOPT_USERPWD, $this->_token);
		curl_setopt($this->_curlConn, CURLOPT_TIMEOUT, 15);

		/***
		 * Determines if the cURL CURLOPT_SSL_VERIFYPEER option is enabled.
		 *
		 * @since 2.5
		 *
		 * @param bool is_enabled True to enable peer verification. False to bypass peer verification. Defaults to true.
		 */
		$verify_peer = apply_filters( 'gform_freshbooks_verifypeer', true );
		curl_setopt($this->_curlConn, CURLOPT_SSL_VERIFYPEER, $verify_peer );

		/***
		 * Determines if the cURL CURLOPT_SSL_VERIFYHOST option is enabled.
		 *
		 * @since 2.5
		 *
		 * @param bool is_enabled True to enable host verification. False to bypass host verification. Defaults to true.
		 */
		$verify_host = apply_filters( 'gform_freshbooks_verifyhost', true );
		curl_setopt($this->_curlConn, CURLOPT_SSL_VERIFYHOST, $verify_host ? 2 : 0 );

		curl_setopt($this->_curlConn, CURLOPT_USERAGENT, "FreshBooks API AJAX tester 1.0");
		return $this;
	}

/**
 * send request to the server
 */
	public function send($content)
	{
		curl_setopt($this->_curlConn, CURLOPT_POSTFIELDS, $content);
		$result = curl_exec($this->_curlConn);
		return $result;
	}

/**
 * get last error
 */
	public function getLastError()
	{
		return curl_error($this->_curlConn);
	}

}
