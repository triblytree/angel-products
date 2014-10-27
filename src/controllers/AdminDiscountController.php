<?php namespace Angel\Products;

use Illuminate\Database\Eloquent\Collection;
use Input, App, Redirect;

class AdminDiscountController extends \Angel\Core\AdminCrudController {

	protected $Model	= 'Discount';
	protected $uri		= 'discounts';
	protected $plural	= 'discounts';
	protected $singular	= 'discount';
	protected $package	= 'products';

	// Columns to update on edit/add
	protected static function columns()
	{
		return array(
			'name',
			'code',
			'type',
			'rate',
			'user_id',
			'onetime',
		);
	}

	public function validate_rules($id = null)
	{
		return array(
			'name' => 'required',
			'code' => 'required',
			'type' => 'required',
			'rate' => 'required',
		);
	}
}
