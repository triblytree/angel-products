<?php namespace Angel\Products;

use Angel\Core\LinkableModel;

class Discount extends LinkableModel {
	// Columns to update on edit/add
	public static function columns()
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

	public function validate_rules()
	{
		return array(
			'name' => 'required',
			'code' => 'required',
			'type' => 'required',
			'rate' => 'required',
		);
	}
	
	///////////////////////////////////////////////
	//               Menu Linkable               //
	///////////////////////////////////////////////
	public function link()
	{
		return admin_url('discounts/edit/' . $this->id);
	}
	public function link_edit()
	{
		return admin_url('discounts/edit/' . $this->id);
	}
	public function search($terms)
	{
		return static::where(function($query) use ($terms) {
			foreach ($terms as $term) {
				$query->orWhere('name', 'like', $term);
				$query->orWhere('code', 'like', $term);
			}
		})->get();
	}

}