<?php namespace Angel\Products;

use Config, App, View, Input, Redirect, Validator, ToolBelt, Session, Auth, Mail;
use Stripe, Stripe_Charge, Stripe_CardError;

class ProductController extends \Angel\Core\AngelController {
	
	public function __construct()
	{
		parent::__construct();
		
		$this->Cart = $this->data['Cart'] = App::make('Cart');
	}

	public function category($slug)
	{
		// Category
		$ProductCategory = App::make('ProductCategory');
		$this->data['category'] = $ProductCategory->where('slug',$slug)->firstOrFail();

		// View
		return View::make('products::products.category', $this->data);
	}

	public function view($slug)
	{
		$Product         = App::make('Product');
		$ProductCategory = App::make('ProductCategory');

		$product    = $Product::with('images', 'options')->where('slug', $slug)->firstOrFail();
		$categories = $ProductCategory::orderBy('parent_id')->orderBy('order')->get();

		$options = array();
		foreach ($product->options as $option) {
			foreach ($option->items as $item) {
				$options[$item->id]['price'] = $item->price;
				$options[$item->id]['qty']   = $item->qty;
			}
		}

		$this->data['product'] = $product;
		$this->data['options'] = $options;
		$this->data['crumbs']  = $ProductCategory::crumbs($categories, $product->categories()->first()->id, url('products/categories/{slug}'));

		return View::make('products::products.view', $this->data);
	}

	public function cart()
	{
		return View::make('products::products.cart', $this->data);
	}

	public function cart_add()
	{
		$Product = App::make('Product');
		$product = $Product::with('images', 'options')->findOrFail(Input::get('product_id'));
		$product->markSelectedOptions(Input::get('options'));
		
		// Add
		$this->Cart->add($product, Input::get('qty'));
		
		// Error
		if($this->Cart->error()) {
			return Redirect::back()->with('error', $this->Cart->error())->withInput();
		}
		// Success
		else {
			return Redirect::back()->with('success', array(
				'This product has been added to your cart!',
				'<a href="' . url('cart') . '">View Cart</a>'
			))->withInput(); // With input so that the options drop-downs stay the same.
		}
	}

	public function cart_qty()
	{
		foreach (Input::get('qty') as $key=>$qty) {
			$this->Cart->quantity($key, $qty);
		}

		return number_format($this->Cart->total(), 2);
	}

	public function cart_remove($key)
	{
		$this->Cart->remove(urldecode($key));

		return Redirect::to('cart');
	}

	public function checkout()
	{
		if (!$this->Cart->count()) return Redirect::to('cart');
		return View::make('products::products.checkout', $this->data);
	}

	public function charge()
	{
		// Values
		$values = Input::all();
		if($values['sameAddress'] == "yes") {
			foreach($values as $k => $v) {
				if(substr($k,0,9) == "shipping_") $values["billing_".substr($k,9)] = $v;
			}
		}
		if($values['shipping_country'] == "United_States") $values['shipping_country'] = "US";
		if($values['billing_country'] == "United_States") $values['billing_country'] = "US";
		
		// Validate
		$error = NULL;
		$validator_rules = array(
			'email'            => 'required|email',
			'billing_name'     => 'required',
			'billing_address'  => 'required',
			'billing_city'     => 'required',
			'billing_state'    => 'required|size:2',
			'billing_zip'      => 'required',
			'shipping_name'    => 'required',
			'shipping_address' => 'required',
			'shipping_city'    => 'required',
			'shipping_state'   => 'required|size:2',
			'shipping_zip'     => 'required',      
			'card_number'      => 'required',
			'card_expiration_month' => 'required',
			'card_expiration_year' => 'required',
			'card_code'  	   => 'required',
		);
		$validator = Validator::make($values, $validator_rules);
		if ($validator->fails()) {
			foreach($validator->messages()->all() as $error) {
				$error .= '<p>' . $error . '</p>';
			}
		}

		if (!$this->Cart->enoughInventory()) {
			$error = 'inventory_fail';
		}
		
		// Method
		$method = Config::get('products::method');
		
		// Stripe
		if($method == "stripe") {
			// Missing token
			if (!Input::get('stripeToken')) {
				$errors = 'The Stripe token was not generated correctly.';
			}
			
			// Error
			if($error) return $error;
	
			Stripe::setApiKey(Config::get('products::stripe.' . $this->settings['stripe']['value'] . '.secret'));
	
			try {
				$charge = Stripe_Charge::create(array(
					'amount'   => ToolBelt::pennies($this->Cart->total()),
					'currency' => 'usd',
					'card'     => Input::get('stripeToken')
				));
				$charge_id = $charge->id;
			} catch (Stripe_CardError $e) {
				return $e->getMessage();
			}
		}
		// Credit Card
		if($method == "creditcard") {
			// Error
			if($error) return Redirect::to('checkout')->withInput()->withError($error);
			
			// Config
			$c = array(
				'test' => ($this->data['settings']['stripe']['value'] == 'test' ? 1 : 0),
				'debug' => 1,
			);
			
			// Payment class
			$Payment = new Payment();
			
			// Gateway
			$gateway = Config::get('products::gateway');
			if($gateway == "authorize") {
				if($c['test']) {
					$login_id = "3fQKx54hVX6";
					$transaction_key = "3J649gr59u5UVkJB";
				}
				else {
					$login_id = Config::get('products::logins.authorize.login_id');	
					$transaction_key = Config::get('products::logins.authorize.transaction_key');	
				}
				$PaymentAuthorize = new PaymentAuthorize($login_id,$transaction_key,$c);
				$Payment->gateway($PaymentAuthorize);
			}
			
			// Address
			list($first,$last) = explode(' ',$values['billing_name'],2);
			$address = array(
				'first_name' => $first,
				'last_name' => $last,
				'address' => $values['billing_address'],
				'address_2' => $values['billing_address_2'],
				'city' => $values['billing_city'],
				'state' => $values['billing_state'],
				'zip' => $values['billing_zip'],
				'country' => $values['billing_country'],
				'phone' => $values['billing_phone'],
				'email' => $values['email'],
			);
			$Payment->address($address);
			
			// Card
			$card = array(
				'number' => $values['card_number'],
				'expiration_month' => $values['card_expiration_month'],
				'expiration_year' => $values['card_expiration_year'],
				'code' => $values['card_code'],
			);
			$Payment->card($card);
			
			// Amount
			$Payment->amount($this->Cart->total());
			
			// Charge
			$results = $Payment->charge();	
			
			// Success
			if($results['result']) {
				$charge_id = $results['transaction'];
			}
			// Error
			else {
				return Redirect::to('checkout')->withInput()->withError($results['message']);	
			}
		}

		$this->Cart->subtractInventory();

		$Order            = App::make('Order');
		$order            = new $Order;
		$order->email     = Input::get('email');
		$order->charge_id = $charge_id;
		$order->total     = $this->Cart->total();

		if (Input::get('billing_zip')) {
			$billing = array(
				'name'      => Input::get('billing_name'),
				'address'   => Input::get('billing_address'),
				'address_2' => Input::get('billing_address_2'),
				'city'      => Input::get('billing_city'),
				'state'     => Input::get('billing_state'),
				'zip'       => Input::get('billing_zip'),
				'phone'     => Input::get('billing_phone'),
			);
			$order->billing_address = json_encode($billing);
		}

		$shipping = array(
			'name'      => Input::get('shipping_name'),
			'address'   => Input::get('shipping_address'),
			'address_2' => Input::get('shipping_address_2'),
			'city'      => Input::get('shipping_city'),
			'state'     => Input::get('shipping_state'),
			'zip'       => Input::get('shipping_zip'),
			'phone'     => Input::get('shipping_phone'),
		);
		$order->shipping_address = $charge->metadata['shipping'] = json_encode($shipping);

		if (Auth::check()) {
			$order->user_id = Auth::user()->id;
		}

		$order->cart = json_encode($this->Cart->export());
		$order->save();

		if($method == "stripe") {
			$charge->metadata['order_id'] = $order->id;
			$charge->save();
		}
		
		// Discounts - if 'onetime', mark as 'used'
		if($discounts = $this->Cart->discounts()) {
			$Discount = App::make('Discount');
			foreach($discounts as $k => $v) {
				if($v['id']) {
					$discount = $Discount::find($v['id']);
					if($discount->onetime) {
						$discount->used = 1;
						$discount->save();
					}
				}
			}
		}

		Session::put('just-ordered', $order->id);
		$this->Cart->destroy();

		$this->data['order'] = $order;

		$this->email_receipt($order);
		
		if($method == "stripe") return 1;
		else return Redirect::to('order-summary');	
	}

	public function email_receipt($order)
	{
		if(!isset($this->data['order'])) $this->data['order'] = $order; // Sometimes I just call this method when testing
		// Customer
		Mail::send('products::orders.emails.receipt', $this->data, function($message) use ($order) {
			$message->to($order->email)->subject('Receipt for Order #' . $order->id);
		});
		// Admin
		$this->data['admin'] = 1;
		Mail::send('products::orders.emails.receipt', $this->data, function($message) use ($order) {
			$message->to(Config::get('mail.from.address'))->subject('Receipt for Order #' . $order->id);
		});
	}

	public function inventory_fail()
	{
		return Redirect::to('cart')->withErrors('Apologies!  Our inventory is not sufficient to satisfy this order.  Someone purchased the product(s) just before you did!  Shoot!  We have adjusted the product(s) quantities and/or removed them from your cart.  Please verify these new quantities and proceed with the checkout again if you are satisfied.  Your card has not been charged.');
	}

	public function order_summary()
	{
		if (!Session::get('just-ordered')) {
			return Redirect::to('/');
		}

		$Order = App::make('Order');
		$this->data['order'] = $Order::findOrFail(Session::get('just-ordered'));
		return View::make('products::orders.summary', $this->data);
	}

	public function tax()
	{
		// Rate - default to 0, aka no tax
		$rate = 0;
		
		// Rates
		$rates = Config::get('products::tax');
		if($rates) {
			// Billing / shipping match (shouldn't be the case, but that's what the client want so, fuck it)
			$state = strtoupper(Input::get('shipping'));
			if($state == strtoupper(Input::get('billing'))) {
				// State rate
				if(isset($rates[$state])) $rate = $rates[$state];
			}
		}
		
		// Set tax
		$this->Cart->tax($rate);
		
		// Return
		$return = array(
			'tax' => number_format($this->Cart->totalTax(),2),
			'total' => number_format($this->Cart->total(),2)
		);
		return json_encode($return);
	}

}