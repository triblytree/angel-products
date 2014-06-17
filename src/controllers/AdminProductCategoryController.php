<?php

class AdminProductCategoryController extends AdminCrudController {

	protected $model	= 'ProductCategory';
	protected $uri		= 'products/categories';
	protected $plural	= 'categories';
	protected $singular	= 'category';
	protected $package	= 'products';

	public function index()
	{
		$temp_categories = ProductCategory::orderBy('parent_id')->orderBy('order')->get();
		$categories = array();
		foreach ($temp_categories as $category) {
			echo '';
			echo '';
		}

		$this->data['categories'] = $categories;

		return View::make($this->view('index'), $this->data);
	}

	/**
	 * @param int $id - The ID of the model when editing, null when adding.
	 * @return array - Rules for the validator.
	 */
	public function validate_rules($id = null)
	{
		return array(
			'name' => 'required'
		);
	}

}