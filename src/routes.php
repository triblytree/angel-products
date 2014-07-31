<?php

Route::get('products/{slug}', 'ProductController@view');
Route::post('cart-add', array(
	'before' => 'csrf',
	'uses' => 'ProductController@cart_add'
));
Route::post('cart-qty', array(
	'before' => 'csrf',
	'uses' => 'ProductController@cart_qty'
));
Route::get('cart-remove/{key}', 'ProductController@cart_remove');
Route::get('cart', 'ProductController@cart');
Route::get('checkout', 'ProductController@checkout');
Route::post('checkout', array(
	'before' => 'csrf',
	'uses' => 'ProductController@pay'
));
Route::get('order-summary', 'ProductController@order_summary');

Route::group(array('prefix'=>admin_uri('orders'), 'before'=>'admin'), function() {
	$controller = 'AdminOrderController';

	Route::get('/', $controller . '@index');
	Route::get('show/{id}', $controller . '@show');
});

Route::group(array('prefix'=>admin_uri('products'), 'before'=>'admin'), function() {

	$controller = 'AdminProductController';

	Route::get('/', function() {
		Session::reflash();
		return Redirect::to(admin_uri('products/categories'));
	});
	Route::get('add', $controller . '@add');
	Route::post('add', array(
		'before' => 'csrf',
		'uses' => $controller . '@attempt_add'
	));
	Route::get('edit/{id}', $controller . '@edit');
	Route::post('edit/{id}', array(
		'before' => 'csrf',
		'uses' => $controller . '@attempt_edit'
	));
	Route::post('hard-delete/{id}', array(
		'before' => 'csrf',
		'uses' => $controller . '@hard_delete'
	));

	Route::group(array('prefix'=>'categories'), function() {

		$controller = 'AdminProductCategoryController';

		Route::get('/', $controller . '@index');
		Route::get('add', $controller . '@add');
		Route::post('add', array(
			'before' => 'csrf',
			'uses' => $controller . '@attempt_add'
		));
		Route::get('edit/{id}', $controller . '@edit');
		Route::post('edit/{id}', array(
			'before' => 'csrf',
			'uses' => $controller . '@attempt_edit'
		));
		Route::post('delete/{id}', array(
			'before' => 'csrf',
			'uses' => $controller . '@delete'
		));
		Route::post('hard-delete/{id}', array(
			'before' => 'csrf',
			'uses' => $controller . '@hard_delete'
		));
		Route::get('restore/{id}', $controller . '@restore');
		Route::post('update-tree', array(
			'uses' => $controller . '@update_tree'
		));
		Route::get('show-products/{id}', $controller . '@show_products');
	});
});