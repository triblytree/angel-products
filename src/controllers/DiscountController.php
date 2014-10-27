<?php namespace Angel\Products;

use App, Input, Auth;

class DiscountController extends \Angel\Core\AngelController {

	public function apply()
	{
		$result = array();
		$Discount = App::make('Discount');
		$discount = $Discount->where('code','like',Input::get('code'))->first();
		if(is_null($discount)) $result['error'] = "We have no record of this promo code.";
		else if($discount->onetime and $discount->used) $result['error'] = "This promo code has already been used.";
		else if($discount->user_id and Auth::id() != $discount->user_id) $result['error'] = "You don't have permission to use this promo code.";
		else {
			$Cart = App::make('Cart');
			$Cart->discount($discount->rate,$discount->type,array('name' => $discount->name,'id' => $discount->id));
			$result['total'] = number_format($Cart->total(),2);
			$result['discount'] = number_format($Cart->totalDiscount(),2);
		}
		return json_encode($result);
	}
	
}