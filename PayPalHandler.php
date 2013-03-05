<?php
/**
* PayPal IPN Listener
*
* A class to listen for and handle Instant Payment Notifications (IPN). To be used by Paydot's affiliate networks's merchatns
* @package PayPalHandler
* @author Bobby Kostadinov 
* @version 1.0
*/
class PayPalHandler {
    	
	protected $isIpnVerified = false;
	
	protected $response;
	/**
	* True by default. This would force the response back to paypal over SSL
	* @var boolean
	*/
    public $use_ssl = true;
    
    /**
	* Set to true, SANDBOX_HOST will be used for the post back to PayPal
	*
	* @var boolean
	*/
    public $test = false;
    
    /**
	* Tiem out for PayPal's response. Default 30 seconds.
	* @var int
	*/
    public $timeout = 30;
    
    /**
     * Data coming from PayPal
     * @var array
     */
    public $data = null;
        
    const PAYPAL_HOST = 'www.paypal.com';
    
    const SANDBOX_HOST = 'www.sandbox.paypal.com';
    
	/**
	 * 
	 * @param string $postData OPTIONAL post data. If null, 
	 * 				the class automatically reads incoming POST data 
	 * 				from the input stream
	 */
	public function __construct($postData = '')
	{
		if (true !== $this->_curlEnabled()) {
			throw new Exception ('cURL is not available on this server.');
		}
		
		if($postData == '') {
			// reading posted data from directly from $_POST may causes serialization issues with array data in POST
			// reading raw POST data from input stream instead.			
			$postData = file_get_contents('php://input');
		}
		
		$rawPostArray = explode('&', $postData);		
		foreach ($rawPostArray as $keyValue) {
			$keyValue = explode ('=', $keyValue);
			if (count($keyValue) == 2)
				$this->data[$keyValue[0]] = urldecode($keyValue[1]);
		}
	}
    	
    /**
	* cURL PostBack to verify the request from PayPal
	* @return bool
	*/
    protected function verifyIPN()
    {
    	
    	$url = 'https://' . $this->getPayPalHost() . '/cgi-bin/webscr';
    	
    	$req = 'cmd=_notify-validate';
    	
    	if (count($this->data) < 0) {
    		return false;
    	}
    	
    	foreach ($this->data as $key => $value) {
    		$value = urlencode(stripslashes($value));
    		$req .= "&$key=$value";
    	}

    	    	
    	$this->isIpnVerified = false;
    		
        $request = curl_init();

		curl_setopt($request, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($request, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($request, CURLOPT_URL, $url);
        curl_setopt($request, CURLOPT_POST, true);
        curl_setopt($request, CURLOPT_POSTFIELDS, $req);
        curl_setopt($request, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($request, CURLOPT_HEADER, false);
        
        if (file_exists(dirname(__FILE__) . '/cacert.pem')) {
        	curl_setopt($request, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
        }
        
        $this->response = curl_exec($request);
        $this->response_status = strval(curl_getinfo($request, CURLINFO_HTTP_CODE));
        
        if(strcmp ( $this->response, "VERIFIED") == 0) {
        	$this->isIpnVerified = true;	
        }
        
        
        return $this->isIpnVerified;
    }
    
    private function _curlEnabled()
    {
    	return function_exists('curl_version');
    }
    
    public function getPayPalHost()
    {
    	if (true === $this->test) {
    		return self::SANDBOX_HOST;
    	}
    	return self::PAYPAL_HOST;
    }
    
    /**
     * Perform the tracking and send the information back to the network
     */
    public function trackSale(Array $options = null)
    {
    	
    	//Validate the IPN request by sending it back to paypal
    	if (false == $this->verifyIPN()) {
    		return false;
    	}
    	//Stop the trackign process if the IPN reqeust is other but Completed payment
    	if ('Completed' != $this->data['payment_status']) {
    		return false;
    	}
    	
    	$params = array(
    			"username"  => $options['username'],
    			"password"  => $options['password'],
    			"sid"       => $options['sid'],
    			"paypal_id" => (string)$this->data['custom'],
    			"oid"       => $this->data['txn_id'],
    			'value'     => $this->data['mc_gross'],
    			'notes'		=> 'PayPal sale. Customer ' . $this->data['first_name'] . ' ' . $this->data['last_name'],
    	);
    	    	 
    	$ch = curl_init();
    	curl_setopt($ch, CURLOPT_URL, $options['url']);
    	 
    	curl_setopt($ch, CURLOPT_HEADER, 1);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    	curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
    	curl_setopt($ch, CURLOPT_POST, 1);
    	curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    	 
    	$curldata = curl_exec($ch);
    	curl_close($ch); 
    }
 
}