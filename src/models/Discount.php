<?php namespace Angel\Products;

use Angel\Core\LinkableModel;

class Discount extends LinkableModel {
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