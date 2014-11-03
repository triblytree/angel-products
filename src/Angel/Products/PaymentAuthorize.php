<?php
namespace Angel\Products;

if(!class_exists('PaymentAuthorize',false)) {
	/**
	 * An 'extension' of the payment class used for processing payments through Authorize.net.
	 *
	 * Notes:
	 * - When capturing an authorized transaction you MUST pass an amount (less than or equal to the quthorize amount).
	 * - The ability to 'credit' a credit card is not a standard feature.  You must have ECC enabled in your Authorize.net account for this to work: http://www.authorize.net/files/ecc.pdf
	 * - You can charge an existing CIM profile by passing 'authorize_cim_profile' and 'authorize_cim_payment' variables to the $payment class at some point.
	 * - In order to process transactions via 'test mode' you must login to Authorize.net (https://account.authorize.net/) and change your account to 'Test Mode'.
	 * 
	 * Custom variables: 0
	 *
	 * Test account:
	 * - transaction key: 3J649gr59u5UVkJB
	 * - login id: 3fQKx54hVX6
	 *
	 * To do
	 * - Support for $payment->custom array
	 * - Return 'custom' variables in returned results (if possible)
	 * - check for required fields before sending?
	 *
	 * @package kraken\payments
	 */
	class PaymentAuthorize {
		/** Stores the login id for your Authorize.net account. */
		public $login_id;
		/** Stores the transaction key for your Authorize.net account. */
		public $transaction_key;
		/** Stores an array of configuration values passed to the class. */
		public $c;
		
		/**
		 * Constructs the class.
		 *
		 * Configuration values (key, type, default - description):
		 * - test, boolean, 0 - Whether or not we want to process transactions via Authorize.net testing 'sandbox'. Note: Your Authorize.net account must be in 'Test Mode'.
		 *
		 * @param string $login_id The login id of your Authorize.net account.
		 * @param string $transaction_key The transaction key of your Authorize.net account.
		 * @param array $c An array of configuration values. Default = NULL
		 */
		function __construct($login_id,$transaction_key,$c = NULL) {
			self::PaymentAuthorize($login_id,$transaction_key,$c);
		}
		function PaymentAuthorize($login_id,$transaction_key,$c = NULL) {
			// Login id
			$this->login_id = $login_id;
			// Transaction key
			$this->transaction_key = $transaction_key;
			
			// Config
			if(!strlen($c['test'])) $c['test'] = 0;
			$this->c = $c;
		}
		
		/**
		 * Authorizes the customer's account for given amount.
		 *
		 * @param object $payment The instance of the payment class which holds the payment/customer info.
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An array of information about the result of the transaction including 'result' (boolean, 1 = succes, 0 = error) and 'message'.
		 */
		function authorize($payment,$c = NULL) {
			// Error
			$error = NULL;
			// Common errors
			if($error_common = $this->errors()) {
				$error = $error_common;
			}
			// No amount passed
			else if(!$payment->amount) {
				$error = "No authorization amount was passed.";
			}
			// No credit card
			else if(!$payment->card['number'] and (!$payment->authorize_cim_profile or !$payment->authorize_cim_payment)) {
				$error = "No credit card was passed for us to authorize.";	
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
				// Send
				$results = $this->send($payment,"authorize",$c);
			}
			
			// Return
			return $results;
		}
		
		/**
		 * Captures a transaction that was previously authorized via the authorize() method.
		 *
		 * @param object $payment The instance of the payment class which holds the payment/customer info.
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An array of information about the result of the transaction including 'result' (boolean, 1 = succes, 0 = error) and 'message'.
		 */
		function capture($payment,$c = NULL) {
			// Error
			$error = NULL;
			// Common errors
			if($error_common = $this->errors()) {
				$error = $error_common;
			}
			// No transaction ID
			else if(!$payment->transaction) {
				$error = "No transaction ID was passed that we could cancel.";
			}
			// No amount passed
			else if(!$payment->amount) {
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
				// Send
				$results = $this->send($payment,"capture",$c);
			}
			
			// Return
			return $results;
		}
		
		/**
		 * Charges the customer a given amount.
		 *
		 * @param object $payment The instance of the payment class which holds the payment/customer info.
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An array of information about the result of the transaction including 'result' (boolean, 1 = succes, 0 = error) and 'message' as well as the transaction id ('transaction').
		 */
		function charge($payment,$c = NULL) {
			// Error
			$error = NULL;
			// Common errors
			if($error_common = $this->errors()) {
				$error = $error_common;
			}
			// No amount passed
			else if(!$payment->amount) {
				$error = "No charge amount was passed.";
			}
			// No credit card
			else if(!$payment->card['number'] and (!$payment->authorize_cim_profile or !$payment->authorize_cim_payment)) {
				$error = "No credit card was passed for us to charge.";	
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
				// Send
				$results = $this->send($payment,"charge",$c);
			}
			
			// Return
			return $results;
		}
		
		/**
		 * Refunds a customer/account a defined amount on a previous transaction.
		 *
		 * @param object $payment The instance of the payment class which holds the payment/customer info.
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An array of information about the result of the transaction including 'result' (boolean, 1 = succes, 0 = error) and 'message'.
		 */
		function refund($payment,$c = NULL) {
			// Error
			$error = NULL;
			// Common errors
			if($error_common = $this->errors()) {
				$error = $error_common;
			}
			// No transaction ID
			else if(!$payment->transaction) {
				$error = "No transaction ID was passed for the transaction we want to refund.";
			}
			// No amount passed
			else if(!$payment->amount) {
				$error = "No refund amount was passed.";
			}
			// No credit card - yes, Authorize.net does require at least the last 4 digits of a customer's credit card to refund all or part of a transaction
			else if(!$payment->card['number'] and (!$payment->authorize_cim_profile or !$payment->authorize_cim_payment)) {
				$error = "No credit card was passed for us to refund this amount to.";	
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
				// Send
				$results = $this->send($payment,"refund",$c);
			}
			
			// Return
			return $results;
		}
		
		/**
		 * Cancels/voids the given transaction.
		 *
		 * @param object $payment The instance of the payment class which holds the payment/customer info.
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An array of information about the result of the transaction including 'result' (boolean, 1 = succes, 0 = error) and 'message'.
		 */
		function cancel($payment,$c = NULL) {
			// Error
			$error = NULL;
			// Common errors
			if($error_common = $this->errors()) {
				$error = $error_common;
			}
			// No transaction ID
			else if(!$payment->transaction) {
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
				// Send
				$results = $this->send($payment,"cancel",$c);
			}
			
			// Return
			return $results;
		}
		
		/**
		 * Credits a customer the given amount.
		 *
		 * @param object $payment The instance of the payment class which holds the payment/customer info.
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An array of information about the result of the transaction including 'result' (boolean, 1 = succes, 0 = error) and 'message'.
		 */
		function credit($payment,$c = NULL) {
			// Error
			$error = NULL;
			// Common errors
			if($error_common = $this->errors()) {
				$error = $error_common;
			}
			// No amount passed
			else if(!$payment->amount) {
				$error = "No credit amount was passed.";
			}
			// No credit card
			else if(!$payment->card['number'] and (!$payment->authorize_cim_profile or !$payment->authorize_cim_payment)) {
				$error = "No credit card was passed for us to credit.";	
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
				// Remove transaction - must NOT be submitted for this to work (if we want to refund on a specific transaction we use the refund() method).
				$payment->transaction = NULL;
				// Send
				$results = $this->send($payment,"credit",$c);
			}
			
			// Return
			return $results;
		}
		
		/**
		 * Sends various 'transactions' to Authorize.net API.
		 *
		 * @param object $payment The instance of the payment class which holds the payment/customer info.
		 * @param string $action The transaction action we're performing.
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An array of information about the result of the transaction including 'result' (boolean, 1 = succes, 0 = error) and 'message' as well as the transaction id ('transaction').
		 */
		function send($payment,$action,$c = NULL) {
			// Standardize
			$payment = $this->standardize($payment);
			
			// URL
			if($this->c['test']) $url = "https://test.authorize.net/gateway/transact.dll";
			else $url = "https://secure.authorize.net/gateway/transact.dll";
			
			// Convert currency - Authorize.net only allows payments in USD at the moment
			if($payment->currency and $payment->currency != "USD") {
				$payment->amount = $payment->convert_currency($payment->amount,$payment->currency,"USD");
			}
			
			// Transaction type
			if($action == "authorize") $action_code = "AUTH_ONLY";
			if($action == "capture") $action_code = "PRIOR_AUTH_CAPTURE";
			if($action == "charge") $action_code = "AUTH_CAPTURE";
			if($action == "refund") $action_code = "CREDIT";
			if($action == "cancel") $action_code = "VOID";
			if($action == "credit") $action_code = "CREDIT";
			
			// CIM
			$response = NULL;
			$response_cim = 0;
			if(!isset($payment->authorize_cim_profile)) $payment->authorize_cim_profile = NULL;
			if(!isset($payment->authorize_cim_payment)) $payment->authorize_cim_payment = NULL;
			if($payment->authorize_cim_profile and $payment->authorize_cim_payment) {
				// Class
				$cim = new AuthNetCim($this->login_id,$this->transaction_key,$this->c['test']);
				
				// Parameters
				$transaction = 'profileTrans'.str_replace(' ','',ucwords(str_replace('_',' ',strtolower($action_code))));
				$cim->setParameter('transactionType', $transaction);
				$cim->setParameter('transaction_amount', $payment->amount);
				$cim->setParameter('customerProfileId', $payment->authorize_cim_profile);
				$cim->setParameter('customerPaymentProfileId', $payment->authorize_cim_payment);
				if($payment->invoice) $cim->setParameter('order_invoiceNumber', $payment->invoice);
				if($payment->description) $cim->setParameter('order_description', $payment->description);
				
				// Send
				$cim->createCustomerProfileTransactionRequest();
				
				// Debug
				$payment->debug("<b>createCustomerProfileTransactionRequest()</b>",$c['debug']);
				$payment->debug("XML: <xmp>".$cim->xml."</xmp>",$c['debug']);
				$payment->debug("Response: ".$cim->response,$c['debug']);
				$payment->debug("directResponse: ".$cim->directResponse,$c['debug']);
				$payment->debug("validationDirectResponse: ".$cim->validationDirectResponse,$c['debug']);
				$payment->debug("resultCode: ".$cim->resultCode,$c['debug']);
				$payment->debug("code: ".$cim->code,$c['debug']);
				$payment->debug("text: ".$cim->text,$c['debug']);
				$payment->debug("refId: ".$cim->refId,$c['debug']);
				$payment->debug("customerProfileId: ".$cim->customerProfileId,$c['debug']);
				$payment->debug("customerPaymentProfileId: ".$cim->customerPaymentProfileId,$c['debug']);
				$payment->debug("customerAddressId: ".$cim->customerAddressId."<br /><br />",$c['debug']);
				
				preg_match('/<directResponse>(.*?)<\/directResponse>/si',$cim->response,$match);
				$response = str_replace(',','|',$match[1]);
				$response_cim = 1;
				$payment->authorize_cim_save = 0;
			}
			// AIM
			if(!$response and $payment->card['number']) {
				// Values
				$values	= array(
					//"x_test_request" => "TRUE", // Pass this to use test mode on live (non-Test Mode) Authorize.net accounts
					"x_login" => $this->login_id,
					"x_tran_key" => $this->transaction_key,
					"x_version" => "3.1",
					"x_delim_char" => "|",
					"x_delim_data" => "TRUE",
					"x_url" => "FALSE",
					"x_type" => $action_code,
					"x_method" => "CC",
					'x_trans_id' => $payment->transaction,
					"x_relay_response" => "FALSE",
					"x_card_num" => $payment->card['number'],
					"x_exp_date" => $payment->card['expiration'],
					"x_amount" => $payment->amount,
					//"x_currency_code" => $payment['currency'], // Not yet supported by Authorize.net, amount must be in USD
					"x_first_name" => $payment->address['first_name'],
					"x_last_name" => $payment->address['last_name'],
					"x_company" => (isset($payment->address['company']) ? $payment->address['company'] : NULL),
					"x_address" => $payment->address['address_full'],
					"x_city" => $payment->address['city'],
					"x_state" => $payment->address['state'],
					"x_zip"	=> $payment->address['zip'],
					"x_country" => $payment->address['country'],
					"x_email" => (isset($payment->address['email']) ? $payment->address['email'] : NULL),
					"x_phone" => (isset($payment->address['phone']) ? $payment->address['phone'] : NULL),
					"x_fax" => (isset($payment->address['fax']) ? $payment->address['fax'] : NULL),
					"x_invoice_num" => $payment->invoice,
					"x_description" => $payment->description,
					"x_card_code" => $payment->card['code'],
					"x_ship_to_first_name" => (isset($payment->shipping['first_name']) ? $payment->shipping['first_name'] : NULL),
					"x_ship_to_last_name" => (isset($payment->shipping['last_name']) ? $payment->shipping['last_name'] : NULL),
					"x_ship_to_company" => (isset($payment->shipping['company']) ? $payment->shipping['company'] : NULL),
					"x_ship_to_address" => (isset($payment->shipping['address_full']) ? $payment->shipping['address_full'] : NULL),
					"x_ship_to_city" => (isset($payment->shipping['city']) ? $payment->shipping['city'] : NULL),
					"x_ship_to_state" => (isset($payment->shipping['state']) ? $payment->shipping['state'] : NULL),
					"x_ship_to_zip" => (isset($payment->shipping['zip']) ? $payment->shipping['zip'] : NULL),
					"x_ship_to_country" => (isset($payment->shipping['country']) ? $payment->shipping['country'] : NULL),
				);
		
				// Data
				$data = http_build_query($values);
			
				// Send
				$response = $payment->curl($url,$data);
		
				// Debug
				$payment->debug("values: ".json_encode($values),$c['debug']);
				$payment->debug("data: ".$url."?".$data,$c['debug']);
				$payment->debug("response: ".$response,$c['debug']);
			}
			
			// Results
			$ex = explode('|',$response);
			$results = NULL;
			$results['result'] = ($ex[0] === "1" ? 1 : 0);
			$results['message'] = $ex[3];
			$results['transaction'] = ($results['result'] == 1 ? $ex[6] : "");
			$results['action'] = $action;
			$results['test'] = $this->c['test'];
			$results['source'] = "Authorize.net";
			$results['response'] = $response;
			
			// Save to CIM and add to results
			if(!isset($payment->authorize_cim_save)) $payment->authorize_cim_save = 0;
			if($payment->authorize_cim_save and $results['result'] == 1) {
				$results['cim'] = $this->cim_save($payment,$c);
			}
			
			// Used CIM, add to results
			if($response_cim) {
				$results['cim'] = array(
					'profile' => $payment->authorize_cim_profile,
					'payment' => $payment->authorize_cim_payment
				);
			}
			
			// Return
			return $results;
		}
		
		/**
		 * Standardizes some of the values used in Authorize.net.
		 *
		 * @param object $payment The payment object we want to standize the values of.
		 * @return object The standardized payment object.
		 */
		function standardize($payment) {
			// Error
			if(!$payment) return;	
			
			// Amount - x.xx
			$payment->amount = number_format($payment->amount,2,'.','');
			
			// Address
			if($payment->address['address']) {
				$payment->address['address_full'] = $payment->address['address'].($payment->address['address_2'] ? " ".$payment->address['address_2'] : "");
			}
			if($payment->shipping['address']) {
				$payment->shipping['address_full'] = $payment->shipping['address'].($payment->shipping['address_2'] ? " ".$payment->shipping['address_2'] : "");
			}
			
			// Card expiration - MMYY
			if($payment->card['expiration_month'] and $payment->card['expiration_year']) {
				$payment->card['expiration'] = str_pad($payment->card['expiration_month'],2,"0",STR_PAD_LEFT).substr($payment->card['expiration_year'],2,2);
			}
			
			// Return
			return $payment;
		}
		
		/**
		 * Detects some common errors for this gateway and returns the error message.
		 *
		 * @return string The error message (if an error was detected).
		 */
		function errors() {
			// Error
			$error = NULL;
			
			// No login credentials
			if(!$this->login_id or !$this->transaction_key) {
				$error = "The login credentials for Authorize.net are missing.";
			}
			
			// Return
			return $error;
		}

		/**
		 * Send and handles the Authorize.net CIM customer info storage       
		 * 
		 * @param object $payment The instance of the payment class which holds the payment/customer info.
		 * @param array $c An array of configuration values. Default = NULL
		 * @return array An array of information about the saved CIM customer including their 'profile' and 'payment' ids in array('profile' => {customer's profile id},'payment' => {customer's payment id}) format.
		 */
		function cim_save($payment,$c = NULL) {
			// Error
			if(!$payment) return;
			
			// Standardize
			$payment = $this->standardize($payment);
			
			// Class
			$cim = new AuthNetCim($this->login_id,$this->transaction_key,$this->c['test']);
	
			// Credit card
			$cim->setParameter('paymentType','creditcard');
			if($payment->card['number']) $cim->setParameter('cardNumber',$payment->card['number']);
			if($payment->card['code']) $cim->setParameter('cardCode',$payment->card['code']);
			if($payment->card['expiration_year'] and $payment->card['expiration_month']) {
				$cim->setParameter('expirationDate',$payment->card['expiration_year']."-".str_pad($payment->card['expiration_month'],2,0,STR_PAD_LEFT)); // YYYY-MM
			}
			
			// Billing - some information is required and some is optional depending on your Address Verification Service (AVS) settings 
			if($payment->address['first_name']) $cim->setParameter('billTo_firstName',$payment->address['first_name']); // Up to 50 characters (no symbols)
			if($payment->address['last_name']) $cim->setParameter('billTo_lastName',$payment->address['last_name']); // Up to 50 characters (no symbols)
			if($payment->address['company']) $cim->setParameter('billTo_company',$payment->address['company']); // Up to 50 characters (no symbols) (optional)
			if($payment->address['address_full']) $cim->setParameter('billTo_address',$payment->address['address_full']); // Up to 60 characters (no symbols)
			if($payment->address['city']) $cim->setParameter('billTo_city',$payment->address['city']); // Up to 40 characters (no symbols)
			if($payment->address['state']) $cim->setParameter('billTo_state',$payment->address['state']); // A valid two-character state code (US only) (optional)
			if($payment->address['zip']) $cim->setParameter('billTo_zip',$payment->address['zip']); // Up to 20 characters (no symbols)
			if($payment->address['country']) $cim->setParameter('billTo_country',$payment->address['country']); // Up to 60 characters (no symbols) (optional)
			if($payment->address['phone']) $cim->setParameter('billTo_phoneNumber',$payment->address['phone']); // Up to 25 digits (no letters) (optional)
			$cim->setParameter('billTo_faxNumber',$payment->address['fax']); // Up to 25 digits (no letters) (optional)
			
			// Shipping defaults - if no shipping values, use billing ones (shipping is required, see note below)
			if(!$payment->shipping['first_name']) $payment->shipping['first_name'] = $payment->address['first_name'];
			if(!$payment->shipping['last_name']) $payment->shipping['last_name'] = $payment->address['last_name'];
			if(!$payment->shipping['company']) $payment->shipping['company'] = $payment->address['company'];
			if(!$payment->shipping['address']) $payment->shipping['address'] = $payment->address['address_full'];
			if(!$payment->shipping['city']) $payment->shipping['city'] = $payment->address['city'];
			if(!$payment->shipping['state']) $payment->shipping['state'] = $payment->address['state'];
			if(!$payment->shipping['zip']) $payment->shipping['zip'] = $payment->address['zip'];
			if(!$payment->shipping['country']) $payment->shipping['country'] = $payment->address['country'];
			if(!$payment->shipping['phone']) $payment->shipping['phone'] = $payment->address['phone'];
			if(!$payment->shipping['fax']) $payment->shipping['fax'] = $payment->address['fax'];
			
			// Shipping - shipping information is required because it reduces an extra step from having to create a shipping address in the future, therefore you can simply update it when needed. You can populate it with the billing info if you don't have an order form with shipping details.
			if($payment->shipping['first_name']) $cim->setParameter('shipTo_firstName',$payment->shipping['first_name']); // Up to 50 characters (no symbols)
			if($payment->shipping['last_name']) $cim->setParameter('shipTo_lastName',$payment->shipping['last_name']); // Up to 50 characters (no symbols)
			if($payment->shipping['company']) $cim->setParameter('shipTo_company',$payment->shipping['company']); // Up to 50 characters (no symbols) (optional)
			if($payment->shipping['address']) $cim->setParameter('shipTo_address',$payment->shipping['address']); // Up to 60 characters (no symbols)
			if($payment->shipping['city']) $cim->setParameter('shipTo_city',$payment->shipping['city']); // Up to 40 characters (no symbols)
			if($payment->shipping['state']) $cim->setParameter('shipTo_state',$payment->shipping['state']); // A valid two-character state code (US only) (optional)
			if($payment->shipping['zip']) $cim->setParameter('shipTo_zip',$payment->shipping['zip']); // Up to 20 characters (no symbols)
			if($payment->shipping['country']) $cim->setParameter('shipTo_country',$payment->shipping['country']); // Up to 60 characters (no symbols) (optional)
			if($payment->shipping['phone']) $cim->setParameter('shipTo_phoneNumber',$payment->shipping['phone']); // Up to 25 digits (no letters) (optional)
			if($payment->shipping['fax']) $cim->setParameter('shipTo_faxNumber',$payment->shipping['fax']); // Up to 25 digits (no letters) (optional)
			
			// Merchant-assigned reference ID for the request
			//$cim->setParameter('refId','my unique ref id'); // Up to 20 characters (optional)
			
			// merchantCustomerId must be unique across all profiles if defined
			//$cim->setParameter('merchantCustomerId','my unique customer id'); // Up to 20 characters (optional)
			
			// description must be unique across all profiles if defined
			$cim->setParameter('description',"CIM - ".microtime()); // Up to 255 characters (optional)
			
			// A receipt from authorize.net will be sent to the email address defined here
			//$cim->setParameter('email','email@ddress.com'); // Up to 255 characters (optional)
			//$cim->setParameter('customerType','individual'); // individual or business (optional)
			
			// Create Profile
			$cim->createCustomerProfileRequest();
			
			// Debug
			$payment->debug("<b>createCustomerProfileRequest()</b>",$c['debug']);
			$payment->debug("XML: <xmp>".$cim->xml."</xmp>",$c['debug']);
			$payment->debug("Response: ".$cim->response,$c['debug']);
			$payment->debug("directResponse: ".$cim->directResponse,$c['debug']);
			$payment->debug("validationDirectResponse: ".$cim->validationDirectResponse,$c['debug']);
			$payment->debug("resultCode: ".$cim->resultCode,$c['debug']);
			$payment->debug("code: ".$cim->code,$c['debug']);
			$payment->debug("text: ".$cim->text,$c['debug']);
			$payment->debug("refId: ".$cim->refId,$c['debug']);
			$payment->debug("customerProfileId: ".$cim->customerProfileId,$c['debug']);
			$payment->debug("customerPaymentProfileId: ".$cim->customerPaymentProfileId,$c['debug']);
			$payment->debug("customerAddressId: ".$cim->customerAddressId,$c['debug']);
			$payment->debug("errors: ".json_encode($cim->error_messages),$c['debug']);
			$payment->debug("",$c['debug']);		
				
			// Success
			if($cim->isSuccessful()) {
				$results['profile'] = $cim->customerProfileId;
				$results['payment'] = $cim->customerPaymentProfileId;
			}
	
			// Return
			return $results;
		}
	}
}

/**
 * PHP class for interaction with Authorize.net Customer Information Manager (CIM)
 *
 * Developed using PHP 5 (http://www.php.net/) and cURL (http://www.php.net/curl)
 * Tested on PHP versions 4.3.11 and 5.2.5
 *
 * Version 1.3 June 6, 2008
 * Copyright (c) 2007-2008 Website Hosting & Development (http://www.bigdoghost.com)
 * Download Page: http://www.bigdoghost.com/blog/authorizenet-cim/
 * For assistance please contact: 
 * Ray Solomon <support(at)TrafficReGenerator.com> or Josh <josh(at)instantincrease.com>
 * License: http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public License (LGPL)
 *
 * Please keep this header information here
 *
 *
 *
 * Changelog:
 *
 * -- June 6, 2008 - Version 1.3
 * Some misc updates to the php class because of recent changes made to the Authorize.net CIM API.
 * The only change that will affect users of this class is the removal of the Wells Fargo SecureSource implementation.
 * Attached a README.txt file that explains various inconsistencies in the Authorize.net CIM API manual.
 * Added $version and $responseDelimiter variables in the class. Make sure the $responseDelimiter is correct(read below).
 * Updated regex for a few elements that required a dollar amount in the correct format.
 * Please check for updates on our website often.
 *
 * -- March 11, 2008
 * Changed license to GNU LGPL version 3 so this class can be used with proprietary applications.
 * The previous license was GNU GPL version 2.
 *
 * -- January 19, 2008 - Version 1.2
 * This mainly a bug fix release with minor feature enhancements
 * Error handling logic for some functions were incorrect and is now fixed.
 * refId element is now implemented.
 * Wells Fargo SecureSource eCheck.Net implementation was incorrect and is now fixed.
 * echeck (bankAccount element) implementation was incorrect and is now fixed.
 * getCustomerShippingAddress() was modified and renamed to getCustomerShippingAddressRequest().
 * order_purchaseOrderNumber() had and incorrect element name and is now fixed.
 * Some examples were incorrect and is now fixed.
 * Added better examples for each method with full details and all possible parameters.
 * Added Godaddy proxy option for curl. Uncomment the code in the process() function if needed.
 * 
 * -- January 03, 2008 - Version 1.1 
 * This is mainly a bug fix release
 * Some regular expression patterns were incorrect and is now fixed.
 * 
 * -- Dec 20, 2007 - Version 1.0
 * initial release
 *
 * -end of changelog-
 *
 * Notes:
 * 
 * To aid during testing and integration, I added basic error handling to this class so you 
 * can use print_r($cim->error_messages); to see what parameters are required for each method.
 * After you understand what is required and what is optional, then this will prove useful.
 * 
 * In case some of you don't understand why I made the billing and shipping required in
 * createCustomerProfileRequest(). If you don't include shipping information when using 
 * that function, then you will need to create it later anyway because some methods required a 
 * "customerAddressId". Therefore it is wise to just include shipping info in the beginning,
 * that way authorize.net will generate a "customerAddressId" for you to use.
 * 
 * 
 * merchantCustomerId or description is required in these following methods even though the manual 
 * states that each is optional: updateCustomerProfileRequest() and createCustomerProfileRequest().
 *
 * @package kraken\payments
 */
class AuthNetCim {
	
	var $version = '1.3'; // the code revision number for this class
	var $params = array();
	var $LineItems = array();
	var $success = false;
	var $error = true;
	var $error_messages = array();
	var $response;
	var $xml;
	var $update = false;
	var $resultsCode;
	var $code;
	var $text;
	var $refId;
	var $customerProfileId;
	var $customerPaymentProfileId;
	var $customerAddressId;

	var $directResponse;
	var $validationDirectResponse;
	var $responseDelimiter = ','; // Direct Response Delimiter. 
        // Make sure this value is the same in your Authorize.net login area.
        // Account->Settings->Transaction Format Settings->Direct Response

	function AuthNetCim($login, $transkey, $test_mode)
	{
		$this->login = $login;
		$this->transkey = $transkey;
		$this->test_mode = $test_mode;
		
		$subdomain = ($this->test_mode) ? 'apitest' : 'api';
		$this->url = "https://" . $subdomain . ".authorize.net/xml/v1/request.api";
	}
	
	function process($retries = 3)
	{
		// before we make a connection, lets check if there are basic validation errors
		if (count($this->error_messages) == 0)
		{
			$count = 0;
			while ($count < $retries)
			{
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $this->url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
				curl_setopt($ch, CURLOPT_HEADER, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $this->xml);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				// proxy option for godaddy hosted customers (required)
				//curl_setopt($ch, CURLOPT_PROXY,"http://proxy.shr.secureserver.net:3128");
				$this->response = curl_exec($ch);
				$this->parseResults();
				
				if ($this->resultCode == "Ok")
				{
					$this->success = true;
					$this->error = false;
					 break;
				}
				else
				{
					$this->success = false;
					$this->error = true;
					break;
				}
				
				$count++;
			}
		
			curl_close($ch);
		}
		else
		{
			$this->success = false;
			$this->error = true;
		}
	}
	
	
	// This function is used to create a new customer profile along with any 
	// customer payment profiles and customer shipping addresses for the customer profile.
	function createCustomerProfileRequest() {
	$this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<createCustomerProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	<profile>
		" . $this->merchantCustomerId() . "
		" . $this->description() . "
		" . $this->email() . "
		<paymentProfiles>
			" . $this->customerType() . "
			<billTo>
				" . $this->billTo_firstName() . "
				" . $this->billTo_lastName() . "
				" . $this->billTo_company() . "
				" . $this->billTo_address() . "
				" . $this->billTo_city() . "
				" . $this->billTo_state() . "
				" . $this->billTo_zip() . "
				" . $this->billTo_country() . "
				" . $this->billTo_phoneNumber() . "
				" . $this->billTo_faxNumber() . "
			</billTo>
			<payment>
				" . $this->paymentType() . "
			</payment>
		</paymentProfiles>
		<shipToList>
			" . $this->shipTo_firstName() . "
			" . $this->shipTo_lastName() . "
			" . $this->shipTo_company() . "
			" . $this->shipTo_address() . "
			" . $this->shipTo_city() . "
			" . $this->shipTo_state() . "
			" . $this->shipTo_zip() . "
			" . $this->shipTo_country() . "
			" . $this->shipTo_phoneNumber() . "
			" . $this->shipTo_faxNumber() . "
		</shipToList>
	</profile>
	</createCustomerProfileRequest>";
	$this->process();
	}
  
	// This function is used to create a new customer payment profile for an existing customer profile
	function createCustomerPaymentProfileRequest() {
	$this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<createCustomerPaymentProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	" . $this->customerProfileId() . "
	<paymentProfile>
		" . $this->customerType() . "
		<billTo>
			" . $this->billTo_firstName() . "
			" . $this->billTo_lastName() . "
			" . $this->billTo_company() . "
			" . $this->billTo_address() . "
			" . $this->billTo_city() . "
			" . $this->billTo_state() . "
			" . $this->billTo_zip() . "
			" . $this->billTo_country() . "
			" . $this->billTo_phoneNumber() . "
			" . $this->billTo_faxNumber() . "
		</billTo>
		<payment>
			" . $this->paymentType() . "
		</payment>
	</paymentProfile>
	" . $this->validationMode() . "
	</createCustomerPaymentProfileRequest>";
	$this->process();
	}
	
	// This function is used to create a new customer shipping address for an existing customer profile
	function createCustomerShippingAddressRequest() {
	$this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<createCustomerShippingAddressRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	" . $this->customerProfileId() . "
	<address>
		" . $this->shipTo_firstName() . "
		" . $this->shipTo_lastName() . "
		" . $this->shipTo_company() . "
		" . $this->shipTo_address() . "
		" . $this->shipTo_city() . "
		" . $this->shipTo_state() . "
		" . $this->shipTo_zip() . "
		" . $this->shipTo_country() . "
		" . $this->shipTo_phoneNumber() . "
		" . $this->shipTo_faxNumber() . "
	</address>
	</createCustomerShippingAddressRequest>";
	$this->process();
	}
  
	// This function is used to create a payment transaction from an existing customer profile
	function createCustomerProfileTransactionRequest() {
	$this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<createCustomerProfileTransactionRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	<transaction>
		<" . $this->transactionType() . ">
			" . $this->transaction_amount() . "
			" . $this->transactionTax() . "
			" . $this->transactionShipping() . "
			" . $this->transactionDuty() . "
			" . $this->transactionLineItems() . "
			" . $this->customerProfileId() . "
			" . $this->customerPaymentProfileId() . "
			" . $this->customerShippingAddressId() . "
			" . $this->transactionOrder() . "
			" . $this->transactionTaxExempt() . "
			" . $this->transactionRecurringBilling() . "
			" . $this->transactionCardCode() . "
			" . $this->transactionApprovalCode() . "
		</" . $this->transactionType() . ">
	</transaction>
	</createCustomerProfileTransactionRequest>";
	$this->process();
	}
	
	// This function is used to delete an existing customer profile along 
	// with all associated customer payment profiles and customer shipping addresses.
	function deleteCustomerProfileRequest() {
	$this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<deleteCustomerProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	" . $this->customerProfileId() . "
	</deleteCustomerProfileRequest>";
	$this->process();
	}
	
	// This function is used to delete a customer payment profile from an existing customer profile.
	function deleteCustomerPaymentProfileRequest() {
	$this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<deleteCustomerPaymentProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	" . $this->customerProfileId() . "
	" . $this->customerPaymentProfileId() . "
	</deleteCustomerPaymentProfileRequest>";
	$this->process();
	}
	
	// This function is used to delete a customer shipping address from an existing customer profile.
	function deleteCustomerShippingAddressRequest() {
	$this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<deleteCustomerShippingAddressRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	" . $this->customerProfileId() . "
	" . $this->customerAddressId() . "
	</deleteCustomerShippingAddressRequest>";
	$this->process();
	}
	
	// This function is used to retrieve an existing customer profile along 
	// with all the associated customer payment profiles and customer shipping addresses.
	function getCustomerProfileRequest() {
	$this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<getCustomerProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->customerProfileId() . "
	</getCustomerProfileRequest>";
	$this->process();
	}
	
	// This function is used to retrieve a customer payment profile for an existing customer profile.
	function getCustomerPaymentProfileRequest() {
	$this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<getCustomerPaymentProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->customerProfileId() . "
	" . $this->customerPaymentProfileId() . "
	</getCustomerPaymentProfileRequest>";
	$this->process();
	}
	
	// This function is used to retrieve a customer shipping address for an existing customer profile.
	function getCustomerShippingAddressRequest() {
	$this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<getCustomerShippingAddressRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->customerProfileId() . "
	" . $this->customerAddressId() . "
	</getCustomerShippingAddressRequest>";
	$this->process();
	}
	
	// This function is used to update an existing customer profile.
	function updateCustomerProfileRequest() {
	$this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<updateCustomerProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	<profile>
		" . $this->merchantCustomerId() . "
		" . $this->description() . "
		" . $this->email() . "
		" . $this->customerProfileId() . "
	</profile>
	</updateCustomerProfileRequest>";
	$this->process();
	}
	
	// This function is used to update a customer payment profile for an existing customer profile.
	function updateCustomerPaymentProfileRequest() {
	$this->update = false; // keep this false for now
	$this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<updateCustomerPaymentProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	" . $this->customerProfileId() . "
	<paymentProfile>
		" . $this->customerType() . "
		<billTo>
			" . $this->billTo_firstName() . "
			" . $this->billTo_lastName() . "
			" . $this->billTo_company() . "
			" . $this->billTo_address() . "
			" . $this->billTo_city() . "
			" . $this->billTo_state() . "
			" . $this->billTo_zip() . "
			" . $this->billTo_country() . "
			" . $this->billTo_phoneNumber() . "
			" . $this->billTo_faxNumber() . "
		</billTo>
		<payment>
			" . $this->paymentType() . "
		</payment>
	" . $this->customerPaymentProfileId() . "	
	</paymentProfile>
	</updateCustomerPaymentProfileRequest>";
	$this->process();
	}
	
	// This function is used to update a shipping address for an existing customer profile.
	function updateCustomerShippingAddressRequest() {
	$this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<updateCustomerShippingAddressRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	" . $this->customerProfileId() . "
	<address>
		" . $this->shipTo_firstName() . "
		" . $this->shipTo_lastName() . "
		" . $this->shipTo_company() . "
		" . $this->shipTo_address() . "
		" . $this->shipTo_city() . "
		" . $this->shipTo_state() . "
		" . $this->shipTo_zip() . "
		" . $this->shipTo_country() . "
		" . $this->shipTo_phoneNumber() . "
		" . $this->shipTo_faxNumber() . "
		" . $this->customerAddressId() . "
	</address>
	</updateCustomerShippingAddressRequest>";
	$this->process();
	}
	
	// This function is used to verify an existing customer payment profile by generating a test transaction.
	function validateCustomerPaymentProfileRequest() {
	$this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<validateCustomerPaymentProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->customerProfileId() . "
	" . $this->customerPaymentProfileId() . "
	" . $this->customerShippingAddressId() . "
	" . $this->validationMode() . "
	</validateCustomerPaymentProfileRequest>";
	$this->process();
	}
	
	
	
	function parseResults()
	{
		$this->resultCode = $this->substring_between($this->response,'<resultCode>','</resultCode>');
		$this->code = $this->substring_between($this->response,'<code>','</code>');
		$this->text = $this->substring_between($this->response,'<text>','</text>');
		$this->refId = $this->substring_between($this->response,'<refId>','</refId>');
		$this->customerProfileId = $this->substring_between($this->response,'<customerProfileId>','</customerProfileId>');
		$this->customerPaymentProfileId = $this->substring_between($this->response,'<customerPaymentProfileId>','</customerPaymentProfileId>');
		$this->customerAddressId = $this->substring_between($this->response,'<customerAddressId>','</customerAddressId>');
		$this->directResponse = $this->substring_between($this->response,'<directResponse>','</directResponse>');
		$this->validationDirectResponse = $this->substring_between($this->response,'<validationDirectResponse>','</validationDirectResponse>');
		
		// Custom - Original code didn't catch these
		if(!$this->customerPaymentProfileId and strstr($this->response,'customerPaymentProfileIdList')) {
			preg_match('/<customerPaymentProfileIdList><numericString>(.*?)<\/numericString><\/customerPaymentProfileIdList>/si',$this->response,$match);
			$this->customerPaymentProfileId = $match[1];
		}
		
		if (!empty($this->directResponse))
		{
			$array = explode($this->responseDelimiter, $this->directResponse);
			$this->directResponse = $array[3];
		}
		if (!empty($this->validationDirectResponse))
		{
			$array = explode($this->responseDelimiter, $this->validationDirectResponse);
			$this->validationDirectResponse = $array[3];
		}
		
	}
	
	function substring_between($haystack,$start,$end)
	{
		if (strpos($haystack,$start) === false || strpos($haystack,$end) === false)
		{
			return false;
		}
		else
		{
			$start_position = strpos($haystack,$start)+strlen($start);
			$end_position = strpos($haystack,$end);
			return substr($haystack,$start_position,$end_position-$start_position);	
		}
	}
  
	function setParameter($field = "", $value = NULL)
	{
		$this->params[$field] = $value;
	}
	
	function isSuccessful() 
	{
		return $this->success;
	}
	
	// This function will output the proper xml for a paymentType: (echeck or creditcard)
	// The elements within "bankAccount" is still incorrect in the manual. I fixed it here.
	function paymentType()
	{
		if (isset($this->params['paymentType']))
		{
			if (($this->params['paymentType'] == "echeck") 
			|| ($this->params['paymentType'] == "bankAccount"))
			{
				return "
				<bankAccount>
					" . $this->accountType() . "
					" . $this->routingNumber() . "
					" . $this->accountNumber() . "
					" . $this->nameOnAccount() . "
					" . $this->echeckType() . "
					" . $this->bankName() . "
				</bankAccount>";
			}
			elseif (($this->params['paymentType'] == "creditcard")
			|| ($this->params['paymentType'] == "creditCard"))
			{
				return "
				<creditCard>
					" . $this->cardNumber() . "
					" . $this->expirationDate() . "
				</creditCard>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): paymentType is required and must be (bankAccount or creditCard)';
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): paymentType is required and must be (bankAccount or creditCard)';
		}
	}
	
	// Merchant-assigned reference ID for the request (optional)
	function refId()
	{
		if (isset($this->params['refId']))
		{
			if ((strlen($this->params['refId']) > 0) 
			&& (strlen($this->params['refId']) <= 20))
			{
				return "<refId>" . $this->params['refId'] . "</refId>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): refId must be up to 20 characters';
			}
		}
	}
	
	// Contains tax information for the transaction (optional)
	function transactionTax()
	{
		if ((isset($this->params['tax_amount'])) 
		|| (isset($this->params['tax_name'])) 
		|| (isset($this->params['tax_description'])))
		{
			return "
			<tax>
				" . $this->tax_amount() . "
				" . $this->tax_name() . "
				" . $this->tax_description() . "
			</tax>";
		}
	}
	
	// The tax amount for the transaction (optional)
	// This amount must be included in the total amount for the transaction. Ex. 12.99 or 12.9999
	function tax_amount()
	{
		if (isset($this->params['tax_amount']))
		{
			if (preg_match('/(^[0-9]+\.[0-9]{1,4}$)/', $this->params['tax_amount']))
			{
				return "<amount>" . $this->params['tax_amount'] . "</amount>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): tax_amount must be up to 4 digits with a decimal point (no dollar symbol)';
			}
		}
	}
	
	// The name of the tax for the transaction (optional)
	function tax_name()
	{
		if (isset($this->params['tax_name']))
		{
			if ((strlen($this->params['tax_name']) > 0) 
			&& (strlen($this->params['tax_name']) <= 31))
			{
				return "<name>" . $this->params['tax_name'] . "</name>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): tax_name must be up to 31 characters';
			}
		}
	}
	
	// The tax description for the transaction (optional)
	function tax_description()
	{
		if (isset($this->params['tax_description']))
		{
			if ((strlen($this->params['tax_description']) > 0) 
			&& (strlen($this->params['tax_description']) <= 255))
			{
				return "<description>" . $this->params['tax_description'] . "</description>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): tax_description must be up to 255 characters';
			}
		}
	}
	
	// Contains tax information for the transaction (optional)
	function transactionShipping()
	{
		if ((isset($this->params['shipping_amount'])) 
		|| (isset($this->params['shipping_name'])) 
		|| (isset($this->params['shipping_description'])))
		{
			return "
			<shipping>
				" . $this->shipping_amount() . "
				" . $this->shipping_name() . "
				" . $this->shipping_description() . "
			</shipping>";
		}
	}
	
	// The shipping amount for the transaction (optional)
	// This amount must be included in the total amount for the transaction. Ex. 12.99 or 12.9999
	function shipping_amount()
	{
		if (isset($this->params['shipping_amount']))
		{
			if (preg_match('/(^[0-9]+\.[0-9]{1,4}$)/', $this->params['shipping_amount']))
			{
				return "<amount>" . $this->params['shipping_amount'] . "</amount>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): shipping_amount must be up to 4 digits with a decimal point. (no dollar symbol)';
			}
		}
	}
	
	// The name of the shipping for the transaction (optional)
	function shipping_name()
	{
		if (isset($this->params['shipping_name']))
		{
			if ((strlen($this->params['shipping_name']) > 0) 
			&& (strlen($this->params['shipping_name']) <= 31))
			{
				return "<name>" . $this->params['shipping_name'] . "</name>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): shipping_name must be up to 31 characters';
			}
		}
	}
	
	// The shipping description for the transaction (optional)
	function shipping_description()
	{
		if (isset($this->params['shipping_description']))
		{
			if ((strlen($this->params['shipping_description']) > 0) 
			&& (strlen($this->params['shipping_description']) <= 255))
			{
				return "<description>" . $this->params['shipping_description'] . "</description>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): shipping_description must be up to 255 characters';
			}
		}
	}
	
	// Contains duty information for the transaction (optional)
	function transactionDuty()
	{
		if ((isset($this->params['duty_amount'])) 
		|| (isset($this->params['duty_name'])) 
		|| (isset($this->params['duty_description'])))
		{
			return "
			<duty>
				" . $this->duty_amount() . "
				" . $this->duty_name() . "
				" . $this->duty_description() . "
			</duty>";
		}
	}
	
	// The duty amount for the transaction (optional)
	// This amount must be included in the total amount for the transaction. Ex. 12.99 or 12.9999
	function duty_amount()
	{
		if (isset($this->params['duty_amount']))
		{
			if (preg_match('/(^[0-9]+\.[0-9]{1,4}$)/', $this->params['duty_amount']))
			{
				return "<amount>" . $this->params['duty_amount'] . "</amount>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): duty_amount must be up to 4 digits with a decimal point. (no dollar symbol)';
			}
		}
	}
	
	// The name of the duty for the transaction (optional)
	function duty_name()
	{
		if (isset($this->params['duty_name']))
		{
			if ((strlen($this->params['duty_name']) > 0) 
			&& (strlen($this->params['duty_name']) <= 31))
			{
				return "<name>" . $this->params['duty_name'] . "</name>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): duty_name must be up to 31 characters';
			}
		}
	}
	
	// The duty description for the transaction (optional)
	function duty_description()
	{
		if (isset($this->params['duty_description']))
		{
			if ((strlen($this->params['duty_description']) > 0) 
			&& (strlen($this->params['duty_description']) <= 255))
			{
				return "<description>" . $this->params['duty_description'] . "</description>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): duty_description must be up to 255 characters';
			}
		}
	}
	
	// Contains line item details about the order (optional)
	// Up to 30 distinct instances of this element may be included per transaction to describe items included in the order.
	// USAGE: see the example code for createCustomerProfileTransactionRequest() in the examples provided.
	function transactionLineItems()
	{
		if (count($this->LineItems) > 30)
		{
			$this->error_messages[] .= '$object->LineItems: (multidimensional array) Up to 30 distinct instances of this element may be included';
		}
		else
		{
			if (count($this->LineItems) > 0)
			{
				$xmlcode = '';
				foreach($this->LineItems as $items)
				{
					$xmlcode .= "<lineItems>\n";
					foreach ($items as $key=>$value)
					{
						$xmlcode .= "<$key>$value</$key>\n";
					}
					$xmlcode .= "</lineItems>\n";
				}
				return $xmlcode;
			}
		}
	}
	
	// Contains duty information for the transaction (optional)
	function transactionOrder()
	{
		if ((isset($this->params['order_invoiceNumber'])) 
		|| (isset($this->params['order_description'])) 
		|| (isset($this->params['order_purchaseOrderNumber'])))
		{
			return "
			<order>
				" . $this->order_invoiceNumber() . "
				" . $this->order_description() . "
				" . $this->order_purchaseOrderNumber() . "
			</order>";
		}
	}
	
	// The merchant assigned invoice number for the transaction (optional)
	function order_invoiceNumber()
	{
		if (isset($this->params['order_invoiceNumber'])) 
		{
			if ((strlen($this->params['order_invoiceNumber']) > 0) 
			&& (strlen($this->params['order_invoiceNumber']) <= 20))
			{
				return "<invoiceNumber>" . $this->params['order_invoiceNumber'] . "</invoiceNumber>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): order_invoiceNumber must be up to 20 characters (no symbols)';
			}
		}
	}
	
	// The transaction description (optional)
	function order_description()
	{
		if (isset($this->params['order_description'])) 
		{
			if ((strlen($this->params['order_description']) > 0) 
			&& (strlen($this->params['order_description']) <= 255))
			{
				return "<description>" . $this->params['order_description'] . "</description>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): order_description must be up to 255 characters (no symbols)';
			}
		}
	}
	
	// The merchant assigned purchase order number (optional)
	function order_purchaseOrderNumber()
	{
		if (isset($this->params['order_purchaseOrderNumber'])) 
		{
			if ((strlen($this->params['order_purchaseOrderNumber']) > 0) 
			&& (strlen($this->params['order_purchaseOrderNumber']) <= 25))
			{
				return "<purchaseOrderNumber>" . $this->params['order_purchaseOrderNumber'] . "</purchaseOrderNumber>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): order_purchaseOrderNumber must be up to 25 characters (no symbols)';
			}
		}
	}
	
	/************************* Billing Functions *************************/
	
	// The customer's first name (optional)
	function billTo_firstName()
	{
		if (isset($this->params['billTo_firstName']))
		{
			if ($this->update === true)
			{
				return "<firstName>" . $this->params['billTo_firstName'] . "</firstName>";
			}
			else
			{
				if ((strlen($this->params['billTo_firstName']) > 0) && (strlen($this->params['billTo_firstName']) <= 50))
				{
					return "<firstName>" . $this->params['billTo_firstName'] . "</firstName>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): billTo_firstName must be up to 50 characters (no symbols)';
				}
			}
		}
	}
	
	// The customer's last name (optional)
	function billTo_lastName()
	{
		
		if (isset($this->params['billTo_lastName']))
		{
			if ($this->update === true)
			{
				return "<lastName>" . $this->params['billTo_lastName'] . "</lastName>";
			}
			else
			{
				if ((strlen($this->params['billTo_lastName']) > 0) && (strlen($this->params['billTo_lastName']) <= 50))
				{
					return "<lastName>" . $this->params['billTo_lastName'] . "</lastName>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): billTo_lastName must be up to 50 characters (no symbols)';
				}
			}
		}
	}
	
	// The name of the company associated with the customer, if applicable (optional)
	function billTo_company()
	{
		if (isset($this->params['billTo_company']))
		{
			if ($this->update === true)
			{
				return "<company>" . $this->params['billTo_company'] . "</company>";
			}
			else
			{
				if ((strlen($this->params['billTo_company']) > 0) && (strlen($this->params['billTo_company']) <= 50))
				{
					return "<company>" . $this->params['billTo_company'] . "</company>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): billTo_company must be up to 50 characters (no symbols)';
				}
			}
		}
	}
	
	// The customer's address (optional)
	function billTo_address()
	{
		if (isset($this->params['billTo_address']))
		{
			if ($this->update === true)
			{
				return "<address>" . $this->params['billTo_address'] . "</address>";
			}
			else
			{
				if ((strlen($this->params['billTo_address']) > 0) && (strlen($this->params['billTo_address']) <= 60))
				{
					return "<address>" . $this->params['billTo_address'] . "</address>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): billTo_address must be up to 60 characters (no symbols)';
				}
			}
		}
	}
	
	// The city of the customer's address (optional)
	function billTo_city()
	{
		if (isset($this->params['billTo_city']))
		{
			if ($this->update === true)
			{
				return "<city>" . $this->params['billTo_city'] . "</city>";
			}
			else
			{
				if ((strlen($this->params['billTo_city']) > 0) && (strlen($this->params['billTo_city']) <= 40))
				{
					return "<city>" . $this->params['billTo_city'] . "</city>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): billTo_city must be up to 40 characters (no symbols)';
				}
			}
		}
	}
	
	// The state of the customer's address (optional)
	// http://www.usps.com/ncsc/lookups/usps_abbreviations.html#states
	function billTo_state()
	{
		if (isset($this->params['billTo_state']))
		{
			if ($this->update === true)
			{
				return "<state>" . $this->params['billTo_state'] . "</state>";
			}
			else
			{
				if (preg_match('/^[a-z]{2}$/i', $this->params['billTo_state']))
				{
					return "<state>" . $this->params['billTo_state'] . "</state>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): billTo_state must be a valid two-character state code';
				}
			}
		}
	}
	
	// The ZIP code of the customer's address (optional)
	function billTo_zip()
	{
		if (isset($this->params['billTo_zip']))
		{
			if ($this->update === true)
			{
				return "<zip>" . $this->params['billTo_zip'] . "</zip>";
			}
			else
			{
				if ((strlen($this->params['billTo_zip']) > 0) && (strlen($this->params['billTo_zip']) <= 20))
				{
					return "<zip>" . $this->params['billTo_zip'] . "</zip>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): billTo_zip must be up to 20 characters (no symbols)';
				}
			}
		}
	}
	
	// This element is optional
	function billTo_country()
	{
		if (isset($this->params['billTo_country']))
		{
			if ($this->update === true)
			{
				return "<country>" . $this->params['billTo_country'] . "</country>";
			}
			else
			{
				if ((strlen($this->params['billTo_country']) > 0) && (strlen($this->params['billTo_country']) <= 60))
				{
					return "<country>" . $this->params['billTo_country'] . "</country>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): billTo_country must be up to 60 characters (no symbols)';
				}
			}
		}
	}
	
	// The phone number associated with the customer's address (optional)
	function billTo_phoneNumber()
	{
		if (isset($this->params['billTo_phoneNumber']))
		{
			if ($this->update === true)
			{
				return "<phoneNumber>" . $this->params['billTo_phoneNumber'] . "</phoneNumber>";
			}
			else
			{
				if ((strlen($this->params['billTo_phoneNumber']) > 0) && (strlen($this->params['billTo_phoneNumber']) <= 25))
				{
					return "<phoneNumber>" . $this->params['billTo_phoneNumber'] . "</phoneNumber>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): billTo_phoneNumber must be up to 25 digits (no letters). Ex. (123)123-1234';
				}
			}
		}
	}
	
	// This element is optional
	function billTo_faxNumber()
	{
		if (isset($this->params['billTo_faxNumber']))
		{
			if ($this->update === true)
			{
				return "<faxNumber>" . $this->params['billTo_faxNumber'] . "</faxNumber>";
			}
			else
			{
				if ((strlen($this->params['billTo_faxNumber']) > 0) && (strlen($this->params['billTo_faxNumber']) <= 25))
				{
					return "<faxNumber>" . $this->params['billTo_faxNumber'] . "</faxNumber>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): billTo_faxNumber must be up to 25 digits (no letters). Ex. (123)123-1234';
				}
			}
		}
	}
	
	/************************* Shipping Functions *************************/
	
	// The customer's first name (optional)
	function shipTo_firstName()
	{
		if (isset($this->params['shipTo_firstName']))
		{
			if ((strlen($this->params['shipTo_firstName']) > 0) && (strlen($this->params['shipTo_firstName']) <= 50))
			{
				return "<firstName>" . $this->params['shipTo_firstName'] . "</firstName>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): shipTo_firstName must be up to 50 characters (no symbols)';
			}
		}
	}
	
	// The customer's last name (optional)
	function shipTo_lastName()
	{
		if (isset($this->params['shipTo_lastName']))
		{
			if ((strlen($this->params['shipTo_lastName']) > 0) && (strlen($this->params['shipTo_lastName']) <= 50))
			{
				return "<lastName>" . $this->params['shipTo_lastName'] . "</lastName>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): shipTo_lastName must be up to 50 characters (no symbols)';
			}
		}
	}
	
	// The name of the company associated with the customer, if applicable (optional)
	function shipTo_company()
	{
		if (isset($this->params['shipTo_company']))
		{
			if ((strlen($this->params['shipTo_company']) > 0) && (strlen($this->params['shipTo_company']) <= 50))
			{
				return "<company>" . $this->params['shipTo_company'] . "</company>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): shipTo_company must be up to 50 characters (no symbols)';
			}
		}
	}
	
	// The customer's address (optional)
	function shipTo_address()
	{
		if (isset($this->params['shipTo_address']))
		{
			if ((strlen($this->params['shipTo_address']) > 0) && (strlen($this->params['shipTo_address']) <= 60))
			{
				return "<address>" . $this->params['shipTo_address'] . "</address>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): shipTo_address must be up to 60 characters (no symbols)';
			}
		}
	}
	
	// The city of the customer's address (optional)
	function shipTo_city()
	{
		if (isset($this->params['shipTo_city']))
		{
			if ((strlen($this->params['shipTo_city']) > 0) && (strlen($this->params['shipTo_city']) <= 40))
			{
				return "<city>" . $this->params['shipTo_city'] . "</city>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): shipTo_city must be up to 40 characters (no symbols)';
			}
		}
	}
	
	// The state of the customer's address (optional)
	// http://www.usps.com/ncsc/lookups/usps_abbreviations.html#states
	function shipTo_state()
	{
		if (isset($this->params['shipTo_state']))
		{
			if (preg_match('/^[a-z]{2}$/i', $this->params['shipTo_state']))
			{
				return "<state>" . $this->params['shipTo_state'] . "</state>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): shipTo_state must be a valid two-character state code';
			}
		}
	}
	
	// The ZIP code of the customer's address (optional)
	function shipTo_zip()
	{
		if (isset($this->params['shipTo_zip']))
		{
			if ((strlen($this->params['shipTo_zip']) > 0) && (strlen($this->params['shipTo_zip']) <= 20))
			{
				return "<zip>" . $this->params['shipTo_zip'] . "</zip>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): shipTo_zip must be up to 20 characters (no symbols)';
			}
		}
	}
	
	// The country of the customer's address (optional)
	function shipTo_country()
	{
		if (isset($this->params['shipTo_country']))
		{
			if ((strlen($this->params['shipTo_country']) > 0) && (strlen($this->params['shipTo_country']) <= 60))
			{
				return "<country>" . $this->params['shipTo_country'] . "</country>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): shipTo_country must be up to 60 characters (no symbols)';
			}
		}
	}
	
	// The phone number associated with the customer's address (optional)
	function shipTo_phoneNumber()
	{
		if (isset($this->params['shipTo_phoneNumber']))
		{
			if ((strlen($this->params['shipTo_phoneNumber']) > 0) && (strlen($this->params['shipTo_phoneNumber']) <= 25))
			{
				return "<phoneNumber>" . $this->params['shipTo_phoneNumber'] . "</phoneNumber>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): shipTo_phoneNumber must be up to 25 digits (no letters). Ex. (123)123-1234';
			}
		}
	}
	
	// The fax number associated with the customer's address (optional)
	function shipTo_faxNumber()
	{
		if (isset($this->params['shipTo_faxNumber']))
		{
			if ((strlen($this->params['shipTo_faxNumber']) > 0) && (strlen($this->params['shipTo_faxNumber']) <= 25))
			{
				return "<faxNumber>" . $this->params['shipTo_faxNumber'] . "</faxNumber>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): shipTo_faxNumber must be up to 25 digits (no letters). Ex. (123)123-1234';
			}
		}
	}
	
	/************************* Other Functions *************************/
	
	// This element is optional
	// Even though the manual states this is optional, it is actually conditional in a circumstance.
	// You must have either the merchantCustomerId and/or description defined for createCustomerProfileRequest()
	function merchantCustomerId()
	{
		if (isset($this->params['merchantCustomerId']))
		{
			if ((strlen($this->params['merchantCustomerId']) > 0) && (strlen($this->params['merchantCustomerId']) <= 20))
			{
				return "<merchantCustomerId>" . $this->params['merchantCustomerId'] . "</merchantCustomerId>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): merchantCustomerId must be up to 20 characters in length';
			}
		}
	}
	
	// This element is optional
	// Even though the manual states this is optional, it is actually conditional in a circumstance.
	// You must have either the description and/or merchantCustomerId defined for createCustomerProfileRequest()
	function description()
	{
		if (isset($this->params['description']))
		{
			if ((strlen($this->params['description']) > 0) && (strlen($this->params['description']) <= 255))
			{
				return "<description>" . $this->params['description'] . "</description>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): description must be up to 255 characters in length';
			}
		}
	}
	
	// This element is optional
	function email()
	{
		if (isset($this->params['email']))
		{
			if ((strlen($this->params['email']) > 0) && (strlen($this->params['email']) <= 255))
			{
				return "<email>" . $this->params['email'] . "</email>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): email must be up to 255 characters in length';
			}
		}
	}
	
	// This element is optional
	function customerType()
	{
		if (isset($this->params['customerType']))
		{
			if (preg_match('/^(individual|business)$/i', $this->params['customerType']))
			{
				return "<customerType>" . strtolower($this->params['customerType']) . "</customerType>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): customerType must be (individual or business)';
			}
		}
	}
	
	// This element is optional
	function accountType()
	{
		if (isset($this->params['accountType']))
		{
			if ($this->update === true)
			{
				return "<accountType>" . $this->params['accountType'] . "</accountType>";
			}
			else
			{
				if (preg_match('/^(checking|savings|businessChecking)$/', $this->params['accountType']))
				{
					return "<accountType>" . $this->params['accountType'] . "</accountType>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): accountType is required and must be (checking, savings or businessChecking)';
				}
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): accountType is required and must be (checking, savings or businessChecking)..';
		}
	}
	
	// This element is optional
	function nameOnAccount()
	{
		if (isset($this->params['nameOnAccount']))
		{
			if ($this->update === true)
			{
				return "<nameOnAccount>" . $this->params['nameOnAccount'] . "</nameOnAccount>";
			}
			else
			{
				if ((strlen($this->params['nameOnAccount']) > 0) && (strlen($this->params['nameOnAccount']) <= 22))
				{
					return "<nameOnAccount>" . $this->params['nameOnAccount'] . "</nameOnAccount>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): nameOnAccount is required and must be up to 22 characters in length';
				}
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): nameOnAccount is required and must be up to 22 characters in length..';
		}
	}
	
	// This element is optional
	function echeckType()
	{
		if (isset($this->params['echeckType']))
		{
			if ($this->update === true)
			{
				return "<echeckType>" . $this->params['echeckType'] . "</echeckType>";
			}
			else
			{
				if (preg_match('/^(CCD|PPD|TEL|WEB)$/', $this->params['echeckType']))
				{
					return "<echeckType>" . $this->params['echeckType'] . "</echeckType>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): echeckType is required and must be (CCD, PPD, TEL or WEB)';
				}
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): echeckType is required and must be (CCD, PPD, TEL or WEB)..';
		}
	}
	
	// This element is optional
	function bankName()
	{
		if (isset($this->params['bankName']))
		{
			if ($this->update === true)
			{
				return "<bankName>" . $this->params['bankName'] . "</bankName>";
			}
			else
			{
				if ((strlen($this->params['bankName']) > 0) && (strlen($this->params['bankName']) <= 60))
				{
					return "<bankName>" . $this->params['bankName'] . "</bankName>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): bankName is required and must be up to 50 characters in length';
				}
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): bankName is required and must be up to 50 characters in length..';
		}
	}
	
	// This element is required in some functions
	function routingNumber()
	{
		if (isset($this->params['routingNumber']))
		{
			if ($this->update === true)
			{
				return "<routingNumber>" . $this->params['routingNumber'] . "</routingNumber>";
			}
			else
			{
				if (preg_match('/^[0-9]{9}$/', $this->params['routingNumber']))
				{
					return "<routingNumber>" . $this->params['routingNumber'] . "</routingNumber>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): routingNumber is required and must be 9 digits';
				}
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): routingNumber is required and must be 9 digits..';
		}
	}
	
	// This element is required in some functions
	function accountNumber()
	{
		if (isset($this->params['accountNumber']))
		{
			if ($this->update === true)
			{
				return "<accountNumber>" . $this->params['accountNumber'] . "</accountNumber>";
			}
			else
			{
				if (preg_match('/^[0-9]{5,17}$/', $this->params['accountNumber']))
				{
					return "<accountNumber>" . $this->params['accountNumber'] . "</accountNumber>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): accountNumber is required and must be 5 to 17 digits';
				}
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): accountNumber is required and must be 5 to 17 digits..';
		}
	}
	
	// This element is required in some functions
	function cardNumber()
	{
		if (isset($this->params['cardNumber']))
		{
			if ($this->update === true)
			{
				return "<cardNumber>" . $this->params['cardNumber'] . "</cardNumber>";
			}
			else
			{
				if (preg_match('/^[0-9]{13,16}$/', $this->params['cardNumber']))
				{
					return "<cardNumber>" . $this->params['cardNumber'] . "</cardNumber>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): cardNumber is required and must be 13 to 16 digits';
				}
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): cardNumber is required and must be 13 to 16 digits..';
		}
	}
	
	// This element is required in some functions
	function expirationDate()
	{
		if (isset($this->params['expirationDate']))
		{
			if ($this->update === true)
			{
				return "<expirationDate>" . $this->params['expirationDate'] . "</expirationDate>";
			}
			else
			{
				if (preg_match('/^([0-9]{4})-([0-9]{2})$/', $this->params['expirationDate']))
				{
					return "<expirationDate>" . $this->params['expirationDate'] . "</expirationDate>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): expirationDate is required and must be YYYY-MM';
				}
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): expirationDate is required and must be YYYY-MM..';
		}
	}
	
	// This element is required in some functions
	// This amount should include all other amounts such as tax amount, shipping amount, etc. Ex. 12.99 or 12.9999
	function transaction_amount()
	{
		if (isset($this->params['transaction_amount']))
		{
			if (preg_match('/(^[0-9]+\.[0-9]{1,4}$)/', $this->params['transaction_amount']))
			{
				return "<amount>" . $this->params['transaction_amount'] . "</amount>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): transaction_amount is required and must be up to 4 digits with a decimal (no dollar symbol)';
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): transaction_amount is required and must be up to 4 digits with a decimal';
		}
	}
	
	// This element is required in some functions
	function transactionType()
	{
		if (isset($this->params['transactionType']))
		{
			if (preg_match('/^(profileTransCaptureOnly|profileTransAuthCapture|profileTransAuthOnly)$/', $this->params['transactionType']))
			{
				return $this->params['transactionType'];
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): transactionType must be (profileTransCaptureOnly, profileTransAuthCapture or profileTransAuthOnly)';
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): transactionType must be (profileTransCaptureOnly, profileTransAuthCapture or profileTransAuthOnly)';
		}
	}
	
	// This element is required in some functions
	// Payment gateway assigned ID associated with the customer profile
	function customerProfileId()
	{
		if (isset($this->params['customerProfileId']))
		{
			if (preg_match('/^[0-9]+$/', $this->params['customerProfileId']))
			{
				return "<customerProfileId>" . $this->params['customerProfileId'] . "</customerProfileId>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): customerProfileId is required and must be numeric';
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): customerProfileId is required and must be numeric';
		}
	}
	
	// This element is required in some functions
	// Payment gateway assigned ID associated with the customer payment profile
	function customerPaymentProfileId()
	{
		if (isset($this->params['customerPaymentProfileId']))
		{
			if ($this->update === true)
			{
				return "<customerPaymentProfileId>" . $this->params['customerPaymentProfileId'] . "</customerPaymentProfileId>";
			}
			else
			{
				if (preg_match('/^[0-9]+$/', $this->params['customerPaymentProfileId']))
				{
					return "<customerPaymentProfileId>" . $this->params['customerPaymentProfileId'] . "</customerPaymentProfileId>";
				}
				else
				{
					$this->error_messages[] .= 'setParameter(): customerPaymentProfileId is required and must be numeric';
				}
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): customerPaymentProfileId is required and must be numeric..';
		}
	}
	
	// This element is required in some functions, otherwise optional
	// Payment gateway assigned ID associated with the customer shipping address
	// Note: If the customer AddressId is not passed, shipping information will not be included with the transaction.
	function customerAddressId()
	{
		if (isset($this->params['customerAddressId']))
		{
			if (preg_match('/^[0-9]+$/', $this->params['customerAddressId']))
			{
				return "<customerAddressId>" . $this->params['customerAddressId'] . "</customerAddressId>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): customerAddressId is required and must be numeric';
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): customerAddressId is required and must be numeric';
		}
	}
	
	// In validateCustomerPaymentProfileRequest(), customerShippingAddressId() is used in place of customerAddressId().
	// The Authorize.net manual is still incorrect on this.
	// Payment gateway assigned ID associated with the customer shipping address
	// Note: If the customer Shipping AddressId is not passed, shipping information will not be included with the transaction.
	function customerShippingAddressId()
	{
		if (isset($this->params['customerShippingAddressId']))
		{
			if (preg_match('/^[0-9]+$/', $this->params['customerShippingAddressId']))
			{
				return "<customerShippingAddressId>" . $this->params['customerShippingAddressId'] . "</customerShippingAddressId>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): customerShippingAddressId is required and must be numeric';
			}
		}
	}
	
	// This element is optional
	function transactionTaxExempt()
	{
		if (isset($this->params['transactionTaxExempt']))
		{
			if (preg_match('/^(true|false)$/i', $this->params['transactionTaxExempt']))
			{
				return "<taxExempt>" . $this->params['transactionTaxExempt'] . "</taxExempt>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): transactionTaxExempt is required and must be (true or false)';
			}
		}
	}
	
	// This element is optional
	function transactionRecurringBilling()
	{
		if (isset($this->params['transactionRecurringBilling']))
		{
			if (preg_match('/^(true|false)$/i', $this->params['transactionRecurringBilling']))
			{
				return "<recurringBilling>" . $this->params['transactionRecurringBilling'] . "</recurringBilling>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): transactionRecurringBilling must be (true or false)';
			}
		}
	}
	
	// The customer's card code (the three or four-digit number on the back or front of a credit card)
	// Required only when the merchant would like to use the Card Code Verification (CCV) filter (conditional)
	// For more information, please see the Merchant Integration Guide.
	function transactionCardCode()
	{
		if (isset($this->params['transactionCardCode']))
		{
			if (preg_match('/^[0-9]{3,4}$/', $this->params['transactionCardCode']))
			{
				return "<cardCode>" . $this->params['transactionCardCode'] . "</cardCode>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): transactionCardCode must be 3 to 4 digits';
			}
		}
	}
	
	// The authorization code of an original transaction required for a Capture Only (conditional)
	// This element is only required for the Capture Only transaction type.
	function transactionApprovalCode()
	{
		if (isset($this->params['transactionApprovalCode'])) 
		{
			if (($this->transactionType() == "profileTransCaptureOnly") 
			&& (strlen($this->params['transactionApprovalCode']) == 6))
			{
				return "<approvalCode>" . $this->params['transactionApprovalCode'] . "</approvalCode>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): transactionApprovalCode must be 6 characters and transactionType value must be (profileTransCaptureOnly)';
			}
		}
	}
	// This element is required in some functions
	function validationMode()
	{
		if (isset($this->params['validationMode']))
		{
			if (preg_match('/^(none|testMode|liveMode)$/', $this->params['validationMode']))
			{
				return "<validationMode>" . $this->params['validationMode'] . "</validationMode>";
			}
			else
			{
				$this->error_messages[] .= 'setParameter(): validationMode must be (none, testMode or liveMode)';
			}
		}
		else
		{
			$this->error_messages[] .= 'setParameter(): validationMode is required';
		}
	}
}
?>