<?php

return array(
	'method' => 'stripe', // stripe, creditcard
	'gateway' => '', // credit card payment gateway: authorize (Authorize.net)
	'logins' => array(
		'authorize' => array(
			'login_id' => '',
			'transaction_key' => '',
		)
	),
	'stripe' => array(
		'test' => array(
			'secret'      => '',
			'publishable' => ''
		),
		'live' => array(
			'secret'      => '',
			'publishable' => ''
		)
	),
	'tax' => array( // Array of state => rate tax rates
		//'CA' => '.09' 
	),
);