<?php namespace Angel\Products;

use Illuminate\Database\Eloquent\Collection;
use Input, App, Redirect;

class AdminDiscountController extends \Angel\Core\AdminCrudController {

	protected $Model	= 'Discount';
	protected $uri		= 'discounts';
	protected $plural	= 'discounts';
	protected $singular	= 'discount';
	protected $package	= 'products';
}
