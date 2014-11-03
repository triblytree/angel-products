<?php
namespace Angel\Products;

if(!class_exists('Payment',false)) {
	/**
	 * A class for processing payments (charging, cancelling, refunding, etc.) through 'child' payment gateway classes.
	 *
	 * Example - charge():
	 * 	$address = array(
	 * 		'first_name' => 'John',
	 *		'last_name' => 'Doe',
	 *		'address' => '123 Test St',
	 *		'address_2' => 'Apt A',
	 *		'city' => 'Portland',
	 *		'state' => 'OR',
	 *		'zip' => '97214',
	 * 		'country' => 'US'
	 * 	);
	 * 	$card = array(
	 * 		'number' => '4111111111111111',
	 *		'expiration_month' => 3,
	 *		'expiration_year' => 2012,
	 *		'code' => '123'
	 * 	);
	 * 	$amount = 1.23;
	 *
	 * 	$payment = new payment();
	 * 	$payment->address($billing);
	 * 	$payment->card($card);
	 * 	$payment->amount($amount);
	 * 	$payment->gateway(new payment_authorize('transaction_key_goes_here','login_id_goes_here'));
	 * 	$results = $payment->charge();
	 *
	 * Example - cancel():
	 * 	$transaction = "1234567";
	 * 
	 * 	$payment = new payment();
	 * 	$payment->transaction($transaction);
	 * 	$payment->gateway(new payment_authorize('transaction_key_goes_here','login_id_goes_here'));
	 * 	$results = $payment->cancel();
	 *
	 * Example - credit() (without an address):
	 * 	$amount = "1.23";
	 * 	$card = array(
	 * 		'number' => '4111111111111111',
	 *		'expiration_month' => 3,
	 *		'expiration_year' => 2012,
	 *		'code' => '123'
	 * 	);
	 * 
	 * 	$payment = new payment();
	 * 	$payment->card($card);
	 * 	$payment->amount($amount);
	 * 	$payment->gateway(new payment_authorize('transaction_key_goes_here','login_id_goes_here'));
	 * 	$results = $payment->credit();
	 *
	 * Regex
	 * - Billing: \$(first_name|last_name|address|address_2|company|city|state|zip|country|phone|email|fax|website)
	 * - Shipping: \$shipping_(first_name|last_name|address|address_2|company|city|state|zip|country|phone|email|fax|website)
	 * 
	 * Remember
	 * - replace all of old name and code (ex: authorize and Authorize.net) with new one
	 * - take into account each gateways login credentials
	 * - transaction_id => transaction
	 * - total => amount
	 * - pass result, message, transaction, source, and overall response in returned results
	 * - handle testing (including different login credentials, URLs, etc.)
	 *
	 * Dependencies
	 * - None
	 *
	 * @package kraken\payments
	 */
	class Payment {
		/** The instance of the payment gateway class which will actually be processing the payment */
		public $gateway;
		/** An array of billing address information we are using in the transaction */
		public $address;
		/** An array of shipping address information we are using in the transaction */
		public $shipping;
		/** An array of credit card information we are using in the transaction */
		public $card;
		/** The amount we are working with in the transaction */
		public $amount;
		/** The currency all amounts are in for this transaction */
		public $currency = "USD";
		/** An array of information on the total amount of this transaction (ex: subtotal + shipping + tax) */
		public $total;
		/** Stores the invoice # for this particular transaction */
		public $invoice;
		/** Stores the description for this particular transaction */
		public $description;
		/** An array of items in this particular transaction */
		public $items;
		/** An array of custom variables to pass with this transaction. */
		public $custom;
		/** The previous transaction ID we want to effect (needed when calling capture(), refund(), or cancel()) */
		public $transaction;
		/** An array of configuration values passed to the class */
		public $c;
		
		/**
		 * Constructs the class.
		 *
		 * @param array $c An array of configuration values. Default = NULL
		 */
		function __construct($c = NULL) {
			self::Payment($c);
		}
		function Payment($c = NULL) {
			// Config
			$this->c = $c;
		}
		
		/**
		 * Catches non-existant methods and sees if they exist in the gateway's object.
		 *
		 * @param string $method The method that was called.
		 * @param array $params An array of params called.
		 * @return mixed The method's return value.
		 */
		function __call($method,$params) {
			if($this->gateway) {
				if(method_exists($this->gateway,$method)) {
					// Call
					return call_user_func_array(array($this->gateway,$method),$params);
				}	
			}
		}
		
		/**
		 * Stores the payment gateway class which will actually process the payment.
		 *
		 * @param object $object An instance of the payment gateway class we'll use for processing the payment.
		 */
		function gateway($gateway) {
			$this->gateway = $gateway;
		}
		
		/**
		 * Stores the billing address information for this transaction.
		 *
		 * Keys in the array (you can add others if you want):
		 * - first_name
		 * - last_name
		 * - company
		 * - address
		 * - address_2
		 * - city
		 * - state
		 * - zip
		 * - country
		 * - phone
		 * - fax
		 * - email
		 *
		 * Note: billing address information is optional in some gateways and is generally only used for verification purposes.
		 *
		 * @param array $array An array of the billing address information.
		 */
		function address($array) {
			$this->address = $array;
		}
		
		/**
		 * Stores the shipping address information for this transaction.
		 *
		 * Keys in the array (you can add others if you want):
		 * - company
		 * - first_name
		 * - last_name
		 * - address
		 * - address_2
		 * - city
		 * - state
		 * - zip
		 * - country
		 * - phone
		 * - fax
		 * - email
		 *
		 * Note: shipping address information is usually optional in most payment gateways.
		 *
		 * @param array $array An array of the shipping address information.
		 */
		function shipping($array) {
			$this->shipping = $array;
		}
		
		/**
		 * Stores the credit card information for this transaction.
		 *
		 * Keys in the array (you can add others if you want):
		 * - number
		 * - expiration_month
		 * - expiration_year
		 * - code (optional)
		 * - type (optional)
		 *
		 * @param array $array An array of the billing address information.
		 */
		function card($array) {
			$this->card = $array;
		}
		
		/**
		 * Stores the amount we're working with in this transaction.
		 *
		 * @param double $amount The amount we're working with in this transaction.
		 */
		function amount($amount) {
			$this->amount = $amount;
		}
		
		/**
		 * Stores the currency that all amounts in this transactions are in.
		 *
		 * @param string $currency The currency code used in this transaction (ex: USD).
		 */
		function currency($currency) {
			$this->currency = $currency;
		}
		
		/**
		 * Stores the total information for this transaction (e.g. how we got to the total 'amount' value).
		 *
		 * Keys in the array (you can add others if you want):
		 * - subtotal
		 * - discounts
		 * - tax
		 * - shipping
		 * - handling
		 * - insurance
		 *
		 * @param array $array An array of the total information.
		 */
		function total($array) {
			$this->total = $array;
		}
		
		/**
		 * Stores the invoice number for this transaction.
		 *
		 * Note: the invoice number is internal on your end and shouldn't be confused with the 'transaction' value which is the transaction id used by the payment gateway.
		 *
		 * Not every payment gateway accepts an invoice number and it is almost always optional.
		 *
		 * @param string $invoice The invoice number for this transaction.
		 */
		function invoice($invoice) {
			$this->invoice = $invoice;
		}
		
		/**
		 * Stores the description for this transaction.
		 *
		 * Not every payment gateway accepts a description and it is almost always optional.
		 *
		 * @param string $description The description for this transaction.
		 */
		function description($description) {
			$this->description = $description;
		}
		
		/**
		 * Stores an array of items for this transaction.
		 *
		 * Not every payment gateway accepts items.
		 *
		 * Also note, this is barely supported in most of the gateway classes I've created. Only Google Checkout's charge() method currently supports it.
		 *
		 * @param array $items An array of items to add to this transaction. See the item() method for what values are available for each item.
		 */
		function items($items) {
			if(!$items) return;
			
			foreach($items as $item) {
				$this->item($item);
			}
		}
		
		/**
		 * Stores a item for this transaction.
		 *
		 * Not every payment gateway accepts items.
		 *
		 * Also note, this is barely supported in most of the gateway classes I've created. Only Google Checkout's charge() method currently supports it.
		 *
		 * Keys in the array (you can add others if you want):
		 * - name
		 * - description
		 * - price
		 * - quantity
		 * - id
		 * - subscription
		 *   - length: number of 'length units' between subscription payments. Example, if 'length_unit' was 'month' and 'length' was 6, they'd be charged every 6 months.
		 *   - length_unit: unit of measurement between subscription payments (day, week, month, year)
		 *   - times: how many times you want this payment to recur (leave blank for infinite)
		 *
		 * @param array $items An array of items to add to this transaction. See the item() method for what values are available for each item.
		 */
		function item($item) {
			if(!$item) return;
			
			$this->items[] = $item;
		}
		
		/**
		 * Stores an array of custom variables to send along with this transaction.
		 *
		 * Not every payment gateway accepts custom variables and the number they accept varies widely.  See the gateway classes description for the number each gateway allows for.
		 *
		 * @param array $custom An array of custom variables to send along with this transaction.
		 */
		function custom($custom) {
			$this->custom = $custom;
		}
		
		/**
		 * Stores a prevous transaction ID we want to effect with this transaction.
		 * 
		 * Usually this is needed if we're making a capture(), cancel(), or refund() call.
		 *
		 * @param string $transaction The previous transaction ID.
		 */
		function transaction($transaction) {
			$this->transaction = $transaction;
		}
		
		/**
		 * Authorizes the customer's account for given amount.
		 *
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An array of information about the result of the transaction including 'result' (boolean, 1 = succes, 0 = error) and 'message'.
		 */
		function authorize($c = NULL) {
			// Error
			$error = NULL;
			// Common errors
			if($error_common = $this->errors(__FUNCTION__)) {
				$error = $error_common;
			}
			// No amount passed
			else if(!$this->amount) {
				$error = "No authorization amount was passed.";
			}
			
			// Error
			if($error) {
				$results = array(
					'result' => 0,
					'message' => $error
				);	
			}
			// Transaction
			else {
				// Process
				$results = $this->gateway->authorize($this,$c);	
			}
			
			// Return
			return $results;
		}
		
		/**
		 * Captures a transaction that was previously authorized via the authorize() method.
		 *
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An array of information about the result of the transaction including 'result' (boolean, 1 = succes, 0 = error) and 'message'.
		 */
		function capture($c = NULL) {
			// Error
			$error = NULL;
			// Common errors
			if($error_common = $this->errors(__FUNCTION__)) {
				$error = $error_common;
			}
			// No transaction ID
			else if(!$this->transaction) {
				$error = "No transaction ID was passed that we could cancel.";
			}
			// No amount passed
			else if(!$this->amount) {
				$error = "No capture amount was passed.";
			}
			
			// Error
			if($error) {
				$results = array(
					'result' => 0,
					'message' => $error
				);	
			}
			// Transaction
			else {
				// Process
				$results = $this->gateway->capture($this,$c);	
			}
			
			// Return
			return $results;
		}
		
		/**
		 * Charges the customer a given amount.
		 *
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An array of information about the result of the charge including 'result' (boolean, 1 = succes, 0 = error) and 'message' as well as the transaction id and any other information the payment gateway class returned.
		 */
		function charge($c = NULL) {
			// Error
			$error = NULL;
			// Common errors
			if($error_common = $this->errors(__FUNCTION__)) {
				$error = $error_common;
			}
			// No amount passed
			else if(!$this->amount) {
				$error = "No charge amount was passed.";
			}
			
			// Error
			if($error) {
				$results = array(
					'result' => 0,
					'message' => $error
				);	
			}
			// Transaction
			else {
				// Process
				$results = $this->gateway->charge($this,$c);	
			}
			
			// Return
			return $results;
		}
		
		/**
		 * Refunds a customer/account a defined amount on a previous transaction.
		 *
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An array of information about the result of the refund including 'result' (boolean, 1 = succes, 0 = error) and 'message' as well as any other information the payment gateway class returned.
		 */
		function refund($c = NULL) {
			// Error
			$error = NULL;
			// Common errors
			if($error_common = $this->errors(__FUNCTION__)) {
				$error = $error_common;
			}
			// No transaction ID
			else if(!$this->transaction) {
				$error = "No transaction ID was passed for the transaction we want to refund.";
			}
			// No amount passed
			else if(!$this->amount) {
				$error = "No refund amount was passed.";
			}
			
			// Error
			if($error) {
				$results = array(
					'result' => 0,
					'message' => $error
				);	
			}
			// Transaction
			else {
				// Process
				$results = $this->gateway->refund($this,$c);	
				
				// Error - don't want to assume they'd want to credit after a failed refund, let them do that for themselves
				/*if($results['result'] != 1) {
					$results_original = $results;
					$c[transaction_tried][] = "refund";
					
					// Try to credit - if we have a card
					if(!in_array("credit",$c[transaction_tried]) and $this->card) {
						$results = $this->credit($c);
					}
					
					// Fail
					if($results['result'] != 1) {
						$results = $results_original;	
					}
				}*/
			}
			
			// Return
			return $results;
		}
		
		/**
		 * Cancels/voids the given transaction.
		 *
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An array of information about the result of the cancellation including 'result' (boolean, 1 = succes, 0 = error) and 'message' as well as any other information the payment gateway class returned.
		 */
		function cancel($c = NULL) {
			// Error
			$error = NULL;
			// Common errors
			if($error_common = $this->errors(__FUNCTION__)) {
				$error = $error_common;
			}
			// No transaction ID
			else if(!$this->transaction) {
				$error = "No transaction ID was passed that we could cancel.";
			}
			
			// Error
			if($error) {
				$results = array(
					'result' => 0,
					'message' => $error
				);	
			}
			// Transaction
			else {
				// Process
				$results = $this->gateway->cancel($this,$c);
			}
			
			// Return
			return $results;
		}
		
		/**
		 * Credits a customer the given amount.
		 *
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An array of information about the result of the transaction including 'result' (boolean, 1 = succes, 0 = error) and 'message'.
		 */
		function credit($c = NULL) {
			// Error
			$error = NULL;
			// Common errors
			if($error_common = $this->errors(__FUNCTION__)) {
				$error = $error_common;
			}
			// No amount passed
			else if(!$this->amount) {
				$error = "No credit amount was passed.";
			}
			
			// Error
			if($error) {
				$results = array(
					'result' => 0,
					'message' => $error
				);	
			}
			// Transaction
			else {
				// Process
				$results = $this->gateway->credit($this,$c);	
				
				// Error - don't want to assume they'd want to refund after a failed credit, let them do that for themselves
				/*if($results['result'] != 1 and $this->transaction) {
					$results_original = $results;
					$c[transaction_tried][] = "credit";
					
					// Try to refund - if we have a transaction
					if(!in_array("refund",$c[transaction_tried]) and $this->transaction) {
						$results = $this->refund($c);
					}
					
					// Fail
					if($results['result'] != 1) {
						$results = $results_original;	
					}
				}*/
			}
			
			// Return
			return $results;
		}
		
		/**
		 * Detects some common errors and returns the error message.
		 *
		 * @param string $function The function we called this method from.
		 * @return string The error message (if an error was detected).
		 */
		function errors($function) {
			$error = NULL;
			
			// No gateway
			if(!$this->gateway or !is_object($this->gateway)) {
				$error = "No payment gateway was passed.";
			}
			// Method doesn't exist
			else if(!method_exists($this->gateway,$function)) {
				$error = "This payment gateway doesn't include a ".$function." method.";
			}
			
			// Return
			return $error;
		}
	
		/**
		 * Curls a given url and returns the contents.
		 *
		 * Configuration values (key, type, default - description):
		 * - follow, boolean, 1 - Do you want to follow to the location the URL might send you to?
		 * - header, boolean, 0 - Do you want to include headers in the returned results?
		 * - cert, string, NULL - The local path on your server to an SSL certificate (.pem) you want to send along to the URL.
		 *
		 * @param string $url The URL you want to curl.
		 * @param string|array $post Either a string of data or an array of data we want to POST to the URL. Default = NULL
		 * @param array $c An array of configuration values. Deafult = NULL 
		 * @return string The content returned by the curled URL. 
		*/
		function curl($url,$post = NULL,$c = NULL) {
			// Config
			if(!isset($c['follow'])) $c['follow'] = 1;
			if(!isset($c['header'])) $c['header'] = 0;
			if(!isset($c['cert'])) $c['cert'] = NULL;
			
			// Curl
			$ch = curl_init();
			curl_setopt($ch,CURLOPT_URL,$url);
			curl_setopt($ch,CURLOPT_FOLLOWLOCATION,$c['follow']);
			curl_setopt($ch,CURLOPT_HEADER,$c['header']);
			curl_setopt($ch,CURLOPT_TIMEOUT,30);
			curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
			if($post) {
				curl_setopt($ch,CURLOPT_POST,1);  
				curl_setopt($ch,CURLOPT_POSTFIELDS,$post);
			}  
			if($c['cert']) {
				curl_setopt($ch,CURLOPT_SSLCERT,$c['cert']);
     			curl_setopt($ch,CURLOPT_CAINFO,$c['cert']);
			}
			curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);  
			curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);
			curl_setopt($ch,CURLOPT_USERAGENT,$_SERVER['HTTP_USER_AGENT']);
			
			// Execute
			$contents = curl_exec($ch);
			
			// Error
			if(!$contents) {
				$this->debug("Curl error: ".curl_error($ch)." (".curl_errno($ch).")");
			}
			
			// Close
			curl_close($ch);
			
			// Return
			return $contents;
		}
		
		/**
		 * Determines and returns the IP address for the current user.
		 *
		 * @return string The IP address for the current user.
		 */
		function ip() {
			// Check ip from share internet
			if(!empty($_SERVER['HTTP_CLIENT_IP'])) $ip = $_SERVER['HTTP_CLIENT_IP'];
			// Check ip is pass from proxy
			elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			// Default
			else $ip = $_SERVER['REMOTE_ADDR'];
			
			return $ip;
		}
		
		/**
		 * Determines if the passed variable has a value associated with it.
		 * 
		 * Returns true if value exists or false if it's empty:
		 * - 0 = true
		 * - "0" = true
		 * - "false" = true
		 * - false = false
		 * - "" = false
		 * - NULL = false
		 * - "NULL" = true
		 * - true = true
		 * - "true" = true
		 * 
		 * Also works for arrays.
		 * 
		 * @param mixed $value The value we want to check against
		 * @return boolean Whether or not the variable has a value associated with it
		 */
		function x($value) {
			if(is_array($value)) $return = true;
			else if(is_object($value)) $return = true;
			else if(strlen($value) > 0) $return = true;
			else $return = false;
			return $return;
		}
		
		/**
		 * Converts given amount from one currency to another using Google's calculator.					
		 * 
		 * @param int $amount The amount you want to convert.
		 * @param string $from The code of the amount's current currency (example: USD).
		 * @param string $to The code of the currency you want to convert the amount to (example: GBP).
		 * @param array $c An array of configuration values. Default = NULL
		 * @return int The converted amount.
		 */
		function convert_currency($amount,$from,$to,$c = NULL) {
			// Error
			if(!$amount or !$from or !$to) return;
			
			// Same currency
			if($from == $to) return $amount;
			
			// Config
			if(!$this->x($c['round'])) $c['round'] = 1; // Round the currency rate to 2 decimals
			if(!$this->x($c['cache'])) $c['cache'] = 1; // Cache and use cached results
			
			// Debug
			$this->debug("<b>".__CLASS__."->".__FUNCTION__."($amount,$from,$to);</b>");
			$this->debug("c:".json_encode($c));
	
			// Cached
			/*if($c['cache']) {
				if($rate = cache_get('payments/currency/'.$from.'->'.$to)) {
					// Debug
					$this->debug('cached rate: '.$rate);
					
					// Convert
					$amount_converted = $amount / $rate; 
					
					// Round
					if($c['round'] == 1) $amount_converted = round($amount_converted,2); 
					
					// Return
					return $amount_converted;
				}
			}*/
			
			// Convert
			$url = "http://www.google.com/ig/calculator?hl=en&q=".$amount.$from."=?".$to;
			$response = $this->curl($url);
			$obj = json_decode($response);
			$ex = explode(' ',$obj->rhs);
			$amount_converted = $ex[0];
			$rate = $amount / $amount_converted;
			
			// Debug
			$this->debug("url: ".$url);
			$this->debug("results: <xmp>".$response."</xmp>");
			$this->debug("object:".json_encode($obj));
			$this->debug("exploded:".json_encode($ex));
			$this->debug("rate:".$rate);
			$this->debug("amount converted:".$amount_converted);
			
			// Round
			if($c['round'] == 1) $amount_converted = round($amount_converted,2);
			
			// Cache
			/*if($c['cache'] == 1) {
				cache_save('payments/currency/'.$from.'->'.$to,$rate);
			}*/
			
			// Return
			return $amount_converted;
		}
		
		/**
		 * Turns the given array into an XML string.
		 *
		 * @param array $array The array we want to convert to XML.
		 * @param int $indent How much to indent the given line of data. Mostly used by the function when it's calling sub-items. Default = 0
		 * @return string The resulting XML string.
		 */
		function xml_create($array,$indent = 0) {
			// Indent
			$indent_character = "	"; // Tab
			for($x = 0;$x < $indent;$x++) $indent_string .=  $indent_character;
			
			if($array) {
				foreach($array as $k => $v) {
					// Parent
					$xml .= "
".$indent_string."<".$k.">";
					// Child - array
					if(is_array($v)) {
						$xml .= $this->xml_create($v,$indent + 1)."
".$indent_string."</".$k.">";
					}
					// Child - value
					else {
						if(strip_tags($v) != $v) $xml .= "<![CDATA[".$v."]]>";
						else $xml .= $this->xml_escape(s($v));
						$xml .= "</".$k.">";
					}
				}
			}
			
			// Return
			return $xml;
		}
		
		/**
		 * Turns the given XML string into a PHP object using SimpleXML.
		 *
		 * @param string $xml The XML string we want to convert to an array.
		 * @return object The SimpleXML object containing the XML data.
		 */
		function xml_object($xml) {
			// Error
			if(!$xml or substr($xml,0,1) != "<") return;
			
			// Object
			$object = new SimpleXMLElement($xml);
			
			// Return
			return $object;
		}

		/**
		 * Parses given xml string and returns an array of the data contained within it.
		 *
		 * Based on http://www.bin-co.com/php/scripts/xml2array/									
		 * 
		 * @param string $xml The XML string you want to parse.
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An arry of the parsed XML data.
		 */
		function xml_array($xml,$c = NULL) {
			// Error
			if(!$xml) return;
			
			// Config
			if(!x($c[get_attributes])) $c[get_attributes] = 1; // '1' or '0', do you want to include the attributes in the array
			if(!$c['priority']) $c['priority'] = 'tag'; // 'tag' or 'attribute'
			if(!x($c['hard'])) $c['hard'] = 0; // Do you want to return a 'hard' array, numbered keys at every level (not yet supported)
			if(!x($c['debug'])) $c['debug'] = 0; // Debug
			
			// xml_parser_create exists?
			if(!function_exists('xml_parser_create')) {
				$payment->debug("xml_parser_create() function not found!",$c['debug']);
				return array();
			}
		
			// Remove <?xml> declaration
			$xml = preg_replace('/<\?xml(.*?)>/','',$xml);
		
			// Wrap // Parser only gets first value if no single parent div
			$xml = "<____xml____>".$xml."</____xml____>";
			//debug("contents: <xmp>".$xml."</xmp>",$c['debug']);
		
			// Get the XML parser of PHP - PHP must have this module for the parser to work
			$parser = xml_parser_create('');
			xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8"); 
			xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
			xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
			xml_parse_into_struct($parser, trim($xml), $xml_values);
			xml_parser_free($parser);
		
			if(!$xml_values) {
				$payment->debug("Wasn't able to find any xml values.",$c['debug']);
				return; // Hmm...
			}
			//$payment->debug("raw values:",$xml_values,$c['debug']);
		
			// Initializations
			$xml_array = array();
			$parents = array();
			$opened_tags = array();
			$arr = array();
		
			$current = &$xml_array; // Refference
		
			// Go through the tags.
			$repeated_tag_index = array();// Multiple tags with same name will be turned into an array
			foreach($xml_values as $data) {
				unset($attributes,$value);// Remove existing values, or there will be trouble
		
				// This command will extract these variables into the foreach scope
				//  tag(string), type(string), level(int), attributes(array).
				extract($data);// We could use the array by itself, but this cooler.
		
				$result = array();
				$attributes_data = array();
				
				if(isset($value)) {
					if($c['priority'] == 'tag') $result = $value;
					else $result['value'] = $value; // Put the value in a assoc array if we are in the 'Attribute' mode
				}
		
				// Set the attributes too.
				if(isset($attributes) and $c[get_attributes]) {
					foreach($attributes as $attr => $val) {
						if($c['priority'] == 'tag') $attributes_data[$attr] = $val;
						else $result['attr'][$attr] = $val; // Set all the attributes in a array called 'attr'
					}
				}
		
				// See tag status and do the needed.
				if($type == "open") {// The starting of the tag '<tag>'
					$parent[$level-1] = &$current;
					if((!is_array($current) or !in_array($tag, array_keys($current))) and $c['hard'] == 0) { // Insert New tag
						$current[$tag] = $result;
						if($attributes_data) $current[$tag. '_attr'] = $attributes_data;
						$repeated_tag_index[$tag.'_'.$level] = 1;
		
						$current = &$current[$tag];
		
					} else { // There was another element with the same tag name
		
						if(isset($current[$tag][0])) {// If there is a 0th element it is already an array
							$current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
							$repeated_tag_index[$tag.'_'.$level]++;
						} else {// This section will make the value an array if multiple tags with the same name appear together
							$current[$tag] = array($current[$tag],$result);// This will combine the existing item and the new item together to make an array
							$repeated_tag_index[$tag.'_'.$level] = 2;
							
							if(isset($current[$tag.'_attr'])) { // The attribute of the last(0th) tag must be moved as well
								$current[$tag]['0_attr'] = $current[$tag.'_attr'];
								unset($current[$tag.'_attr']);
							}
		
						}
						$last_item_index = $repeated_tag_index[$tag.'_'.$level]-1;
						$current = &$current[$tag][$last_item_index];
					}
	
		
				} elseif($type == "complete") { // Tags that ends in 1 line '<tag />'
					// See if the key is already taken.
					if(!x($current[$tag])) { // New Key
						$current[$tag] = $result;
						$repeated_tag_index[$tag.'_'.$level] = 1;
						if($c['priority'] == 'tag' and $attributes_data) $current[$tag. '_attr'] = $attributes_data;
		
					} else { // If taken, put all things inside a list(array)
						if(isset($current[$tag][0]) and is_array($current[$tag])) {// If it is already an array...
		
							//  ...push the new element into that array.
							$current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
							
							if($c['priority'] == 'tag' and $c[get_attributes] and $attributes_data) {
								$current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
							}
							$repeated_tag_index[$tag.'_'.$level]++;
		
						} else { // If it is not an array...
							$current[$tag] = array($current[$tag],$result); // ...Make it an array using using the existing value and the new value
							$repeated_tag_index[$tag.'_'.$level] = 1;
							if($c['priority'] == 'tag' and $c[get_attributes]) {
								if(isset($current[$tag.'_attr'])) { // The attribute of the last(0th) tag must be moved as well
									
									$current[$tag]['0_attr'] = $current[$tag.'_attr'];
									unset($current[$tag.'_attr']);
								}
								
								if($attributes_data) {
									$current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
								}
							}
							$repeated_tag_index[$tag.'_'.$level]++; // 0 and 1 index is already taken
						}
					}
		
				} elseif($type == 'close') { // End of tag '</tag>'
					$current = &$parent[$level-1];
				}
			}
			
			// Unwrap
			$xml_array = $xml_array[____xml____];
			
			// Return
			return $xml_array;
		}
		
		/**
		 * Escapes characters reservied by XML (&, <, >, ', ") in the given string.		
		 *
		 * Note, we won't escape the string if it's enclosed in a <![CDATA[ tag.			
		 * 
		 * @param string $string The string you want to escape.
		 * @return string The string with reserved characters escaped.
		 */
		function xml_escape($string) {
			if(substr($string,0,9) != "<![CDATA[") {
				$search = array("&","'",'"',"<",">","\t","\n","\r","  ");
				$replace = array("&amp;","&apos;","&quot;","&lt;","&gt;"," "," "," "," ");
				$unreplace = array("&amp;amp;","&apos;apos;","&quot;quot;","&lt;lt;","&gt;gt;");
		
				$string = str_replace($search,$replace,$string); // Covert to htmlentities
				$string = str_replace($unreplace,$replace,$string); // Fix characters that were already htmlentities
			}
			
			// Return
			return $string;				  
		}
		
		/**
		 * Prints the given debugging message if debugging is turned on.
		 * 
		 * @param string $message The debugging message we want to display.
		 * @param boolean $debug_local When we call $this->debug() we can indicate whether debugging is turned on within that specific method (locally). Default = 0
		 */
		function debug($message,$debug_local = 0) {
			$debug = 0;
			if($debug_local) $debug = 1;
			else if($this->c['debug']) $debug = 1;
			else if($this->gateway) {
				if($this->gateway->c['debug']) $debug = 1;	
			}
			
			if($debug) print $message."<br />";
		}
	}
}