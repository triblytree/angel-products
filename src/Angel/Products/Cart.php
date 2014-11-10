<?php namespace Angel\Products;

use Illuminate\Database\Eloquent\Collection;
use Session, App;

class Cart {

	protected $cart;

	function __construct()
	{
		$this->init();
	}

	/**
	 * Retrieve the cart from the session or create it.
	 */
	protected function init()
	{
		if (!Session::has('cart')) Session::put('cart', array());

		$this->cart = Session::get('cart');
		if(!$this->cart) $this->cart = array(
			'items' => array(),
			'discounts' => array(),
			'tax' => 0,
			'error' => NULL,
		);
		
		// Clear error
		$this->cart['error'] = NULL;
	}

	/**
	 * Save the cart back into the session.
	 */
	protected function save()
	{
		Session::put('cart', $this->cart);
	}

	/**
	 * Return an array representing the whole cart cart array.
	 *
	 * @return array - The cart array.
	 */
	public function export()
	{
		return $this->cart;
	}

	/**
	 * Load a cart array in.  This is so we can use the cart getOptions(), etc.
	 * for this class from order summaries and whatnot after the card has been charged
	 * and the cart has been destroy()ed from the session.
	 *
	 * @param array $cart - The cart to load.
	 */
	public function load($cart)
	{
		$this->cart = $cart;
	}

	/**
	 * Empty the cart, removing all items.
	 */
	public function destroy()
	{
		$this->cart = array();
		$this->save();
	}

	/**
	 * Returns (and sets, if $error passed) any existing error that may have occured.
	 *
	 * @param string $error An error you want to store.
	 * @return string The error (if any) that occured.
	 */
	public function error($error = NULL)
	{
		if($error) $this->cart['error'] = $error;
		return $this->cart['error'];
	}

	/**
	 * Create a unique key for the product based on its selected options.
	 *
	 * @param Product &$product - The product we're generating a key for.
	 * @return string $key - The unique key.
	 */
	public function key($product)
	{
		return $product->id . '|' . implode(',', array_keys($product->selected_options));
	}

	/**
	 * Add a product to the cart, or increase its quantity if it's already there.
	 * Be sure to have already executed $product->markSelectedOption({product_option_item_id})
	 * or $product->addCustomOptions({options_array}) before adding the product.
	 *
	 * @param Product $product - The product model object to add.
	 * @param int $qty - How many to add to the cart.
	 * @return string $key - The key for retrieving from the cart.
	 */
	public function add($product, $qty = 1)
	{
		$key = $this->key($product);

		$max_qty    = $product->qty;
		$price      = $product->price;
		$fake_price = $product->fake_price;
		foreach ($product->selected_options as $option) {
			$price += $option['price'];
			if ($fake_price > 0) $fake_price += $option['price'];
			if (isset($option['qty']) && $option['qty']) {
				$max_qty = $option['qty'];
			}
		}

		if (array_key_exists($key, $this->cart['items'])) {
			$desired_qty = $this->cart['items'][$key]['qty'] + $qty;
		} else {
			$desired_qty = $qty;
		}
		
		// Inventory - if not enough, we send error
		if($product->inventory) {
			if($desired_qty > $max_qty) {
				if(!$max_qty) $this->error("This item is currently sold out.");
				else $this->error("There ".($max_qty == 1 ? "is" : "are")." only ".$max_qty." of this item left.");
				return;
			}
		}
		
		// Existing
		if (array_key_exists($key, $this->cart['items'])) {
			$this->cart['items'][$key]['qty'] = $desired_qty;
		}
		// New
		else {
			$this->cart['items'][$key] = array(
				'product'    => $product->toJson(),
				'price'      => $price,
				'fake_price' => $fake_price,
				'qty'        => $desired_qty
			);
			if ($product->inventory) {
				$this->cart['items'][$key]['max_qty'] = $max_qty;
				$this->cart['items'][$key]['qty'] = ($qty > $max_qty) ? $max_qty : $qty;
			}
		}

		$this->save();

		return $key;
	}

	/**
	 * Remove a product from the cart by its unique key.
	 *
	 * @param string $key - The unique key, returned from add().
	 * @return bool - True if succeeded, false if not.
	 */
	public function remove($key)
	{
		if (!array_key_exists($key, $this->cart['items'])) return false;

		unset($this->cart['items'][$key]);
		$this->save();

		return true;
	}

	/**
	 * Retrieve a product from the cart by its unique key.
	 *
	 * @param string $key - The unique key, returned from add().
	 * @return array - The product's cart array with 'product', 'price', and 'qty', or false if it doesn't exist.
	 */
	public function get($key)
	{
		if (!array_key_exists($key, $this->cart['items'])) return false;

		return $this->cart['items'][$key];
	}

	/**
	 * Return the cart items array.
	 *
	 * @return array - The cart items array.
	 */
	public function all()
	{
		return $this->cart['items'];
	}

	/**
	 * Count the items in the cart.
	 * @return int $count
	 */
	public function count()
	{
		$count = 0;
		if($this->cart['items']) {
			foreach ($this->cart['items'] as $item) {
				$count += $item['qty'];
			}
		}
		return $count;
	}

	/**
	 * Retrieve an array of selected options on the item, sorted by order.
	 *
	 * @param string $key - The unique key, returned from add().
	 */
	public function getOptions($key)
	{
		if (!array_key_exists($key, $this->cart['items'])) return false;

		$product = json_decode($this->cart['items'][$key]['product']);

		$options = array();
		foreach ($product->selected_options as $string=>$option) {
			$pieces     = explode(':', $string);
			$group_name = $pieces[0];
			$options[$group_name] = $option;
		}

		uasort($options, function($a, $b) {
			return ($a->order > $b->order);
		});

		return $options;
	}

	/**
	 * Adjust the cart quantity for a product by its unique key.
	 *
	 * @param string $key - The unique key, returned from add().
	 * @param int $quantity - The new quantity.
	 * @return bool - Success true or false.
	 */
	public function quantity($key, $quantity)
	{
		if (!array_key_exists($key, $this->cart['items'])) return false;

		if ($quantity == 0) return $this->remove($key);

		if (isset($this->cart['items'][$key]['max_qty']) && $quantity > $this->cart['items'][$key]['max_qty']) {
			$quantity = $this->cart['items'][$key]['max_qty'];
		}
		$this->cart['items'][$key]['qty'] = $quantity;
		$this->save();

		return true;
	}

	/**
	 * Adjust the cart maximum quantity for a product by its unique key.
	 *
	 * @param string $key - The unique key, returned from add().
	 * @param int $quantity - The new quantity.
	 * @return bool - Success true or false.
	 */
	public function maxQuantity($key, $max_quantity)
	{
		if (!array_key_exists($key, $this->cart['items'])) return false;

		if ($max_quantity == 0) return $this->remove($key);

		$this->cart['items'][$key]['max_qty'] = $max_quantity;
		$this->save();

		return true;
	}
	
	/**
	 * Get the subtotal amount for the cart's contents.
	 *
	 * @return float $total - The subtotal  amount.
	 */
	public function subtotal()
	{
		$total = 0;

		foreach (array_keys($this->cart['items']) as $key) {
			$total += $this->subtotalForKey($key);
		}

		return $total;
	}
	
	/**
	 * Get the subtotal dollar amount for a specific cart product variation.
	 *
	 * @return float $total - The subtotal dollar amount for the cart product, or false if it doesn't exist.
	 */
	public function subtotalForKey($key)
	{
		if (!array_key_exists($key, $this->cart['items'])) return false;
		return $this->cart['items'][$key]['price'] * $this->cart['items'][$key]['qty'];
	}

	/**
	 * Get the total dollar amount for the cart's contents.
	 *
	 * @return float $total - The total dollar amount.
	 */
	public function total()
	{
		$total = 0;

		foreach (array_keys($this->cart['items']) as $key) {
			$total += $this->totalForKey($key);
		}
		
		// Discounts (flat rate, which are applied to cart as a whole, not individual items)
		$total -= $this->totalDiscountFlat();
		if($total < 0) $total = 0;

		return $total;
	}

	/**
	 * Get the total shipping amount for the cart's contents.
	 *
	 * @return float $total - The total shipping amount.
	 */
	public function totalShipping()
	{
		$total = 0;

		foreach (array_keys($this->cart['items']) as $key) {
			$total += $this->shippingForKey($key);
		}

		return $total;
	}

	/**
	 * Get the total discount amount for the cart's contents.
	 *
	 * @return float $total - The total discount amount.
	 */
	public function totalDiscount()
	{
		$total = 0;
		if(count($this->cart['discounts'])) {
			// Item specific
			foreach (array_keys($this->cart['items']) as $key) {
				$total += $this->discountForKey($key);
			}
			
			// Flat rate
			$total += $this->totalDiscountFlat();
		}
				
		return $total;
	}

	/**
	 * Get the total flat rate discount amount for the cart.
	 *
	 * Flat rate discounts apply to the cart as a whole (not individual items) so we calculated it differently.
	 *
	 * @return float $total - The total flat rate discount amount.
	 */
	public function totalDiscountFlat()
	{
		$total = 0;
		if(count($this->cart['discounts'])) {
			foreach($this->cart['discounts'] as $k => $v) {
				if($v['type'] == "flat") {
					$total += $v['rate'];	
				}
			}
		}
				
		return $total;
	}

	/**
	 * Get the total tax amount for the cart's contents.
	 *
	 * @return float $total - The total tax amount.
	 */
	public function totalTax()
	{
		$total = 0;
		if(isset($this->cart['tax'])) {
			foreach (array_keys($this->cart['items']) as $key) {
				$total += $this->taxForKey($key);
			}
		}
				
		return $total;
	}
	
	/**
	 * Get the total dollar amount for a specific cart product variation.
	 *
	 * @return float $total - The total dollar amount for the cart product, or false if it doesn't exist.
	 */
	public function totalForKey($key)
	{
		if (!array_key_exists($key, $this->cart['items'])) return false;
		$price = $this->cart['items'][$key]['price'] * $this->cart['items'][$key]['qty'];
		$price += $this->shippingForKey($key);
		$price += $this->taxForKey($key);
		$price -= $this->discountForKey($key);
		return $price;
	}
	
	/**
	 * Get the shipping amount for a specific cart product variation.
	 *
	 * @return float $total - The total shipping amount for the cart product, or false if it doesn't exist.
	 */
	public function shippingForKey($key)
	{
		if (!array_key_exists($key, $this->cart['items'])) return false;
		$product = json_decode($this->cart['items'][$key]['product']);	
		if(isset($product->shipping)) return $product->shipping * $this->cart['items'][$key]['qty'];
	}
	
	/**
	 * Add discount to cart.
	 *
	 * @param float $rate - The discount rate we want to apply
	 * @param string $type - The type of discount rate: flat [default] or percent
	 * @param array $values - An optional array of values to attach to the discount. Use this for values you may need to recall later (ex: id, code, name, etc.)
	 */
	public function discount($rate,$type = "flat",$values = NULL)
	{
		$values['rate'] = $rate;
		$values['type'] = $type;
		$key = ($values['id'] ? $values['id'] : md5(implode($values)));
		$this->cart['discounts'][$key] = $values;

		$this->save();
	}
	
	/**
	 * Get the discount amount for a specific cart product variation.
	 *
	 * @return float $total - The total discount amount for the cart product, or false if it doesn't exist.
	 */
	public function discountForKey($key)
	{
		if (!array_key_exists($key, $this->cart['items'])) return false;
		
		$discount = 0;
		$product = json_decode($this->cart['items'][$key]['product']);
		if(count($this->cart['discounts'])) {
			foreach($this->cart['discounts'] as $k => $v) {
				// Percent
				if($v['type'] == "percent") {
					$discount += round(($v['rate'] / 100) * $this->cart['items'][$key]['price'],2) * $this->cart['items'][$key]['qty'];
				}
				// Flat rate
				else {
					# Discount is applied to the 'total', not per item, if flat rate
				}
			}
		}
		
		return $discount;
	}
	
	/**
	 * Returns array of 'discounts' in cart.
	 *
	 * @return array An array of 'discounts' in the cart.
	 */
	public function discounts()
	{
		return $this->cart['discounts'];
	}
	
	/**
	 * Returns and (if $rate passed) sets tax rate for cart.
	 *
	 * @param float $rate - The tax rate we want to apply. Default = NULL
	 * @return float The tax rate for the cart.
	 */
	public function tax($rate = NULL)
	{
		// Set
		if(strlen($rate)) {
			$this->cart['tax'] = $rate;
			$this->save();
		}
		
		// Return
		return $this->cart['tax'];
	}
	
	/**
	 * Get the tax amount for a specific cart product variation.
	 *
	 * @return float $total - The total tax amount for the cart product, or false if it doesn't exist.
	 */
	public function taxForKey($key)
	{
		if (!array_key_exists($key, $this->cart['items'])) return false;
		
		$tax = 0;
		if($this->cart['tax']) {
			$tax = round($this->cart['tax'] * $this->cart['items'][$key]['price'] * $this->cart['items'][$key]['qty'],2);
		}
		
		return $tax;
	}

	/**
	 * Get all the cart products and cache them.
	 */
	protected $products = null;
	public function products()
	{
		if ($this->products) return $this->products;

		$Product = App::make('Product');
		$product_ids = array();
		foreach ($this->decoded() as $item) {
			$product_ids[] = $item['product']['id'];
		}
		if (!count($product_ids)) return new Collection;
		$this->products = $Product::whereIn('id', $product_ids)->get();
		return $this->products;
	}


	/**
	 * Get all the selected option items and cache them.
	 */
	protected $optionItems = null;
	public function optionItems()
	{
		if ($this->optionItems) return $this->optionItems;

		$ProductOptionItem = App::make('ProductOptionItem');

		$item_ids = array();
		foreach ($this->decoded() as $item) {
			if (!count($item['product']['selected_options'])) continue;
			foreach ($item['product']['selected_options'] as $selected_option) {
				if (!isset($selected_option['id'])) continue;
				$item_ids[] = $selected_option['id'];
			}
		}
		if (!count($item_ids)) return new Collection;
		$this->optionItems = $ProductOptionItem::whereIn('id', $item_ids)->get();
		return $this->optionItems;
	}

	/**
	 * JSON Decode all the cart products and cache them.
	 */
	protected $decoded = null;
	public function decoded()
	{
		if ($this->decoded) return $this->decoded;

		$this->decoded = $this->cart['items'];
		foreach ($this->decoded as &$item) {
			$item['product'] = json_decode($item['product'], true);
		}

		return $this->decoded;
	}

	/**
	 * Verify that there is still enough inventory to satisfy the current cart.  This is checked
	 * before charging the customer's card.
	 *
	 * @return bool
	 */
	public function enoughInventory()
	{
		$enough = true;

		foreach ($this->decoded() as $key=>$item) {
			if (!isset($item['max_qty'])) continue;

			$product = $this->products()->find($item['product']['id']);
			if (!$product) {
				// Product no longer exists
				$enough = false;
				$this->remove($key);
				continue;
			}
			$selected_option = null;
			if (count($item['product']['selected_options'])) {
				$selected_option = array_shift($item['product']['selected_options']);
			}
			if ($selected_option) {
				$optionItem = $this->optionItems()->find($selected_option['id']);
				if (!$optionItem) {
					// Option no longer exists
					$enough = false;
					$this->remove($key);
					continue;
				}
				if ($optionItem->qty < $item['qty']) {
					// Not enough products of that selected option
					$enough = false;
					$this->quantity($key, $optionItem->qty);
					$this->maxQuantity($key, $optionItem->qty);
					continue;
				}
			} else {
				if ($product->qty < $item['qty']) {
					// Not enough of the product
					$enough = false;
					$this->quantity($key, $product->qty);
					$this->maxQuantity($key, $optionItem->qty);
					continue;
				}
			}
		}

		return $enough;
	}

	/**
	 * Deduct all the cart quantities from the products / option items.
	 * This is to be performed after the card has been charged.
	 */
	public function subtractInventory()
	{
		foreach ($this->decoded() as $item) {
			if (!isset($item['max_qty'])) continue;
			$selected_option = null;
			if (count($item['product']['selected_options'])) {
				$selected_option = array_shift($item['product']['selected_options']);
			}
			if ($selected_option && isset($selected_option['id'])) {
				$optionItem = $this->optionItems()->find($selected_option['id']);
				$optionItem->qty -= $item['qty'];
				$optionItem->save();
			} else {
				$product = $this->products()->find($item['product']['id']);
				$product->qty -= $item['qty'];
				$product->save();
			}
		}
	}

}
