<?php
$cart             = json_decode($order->cart, true);
$billing_address  = json_decode($order->billing_address);
$shipping_address = json_decode($order->shipping_address);

$TempCart = clone App::make('Cart');
$TempCart->load($cart);
?>
<div class="row">
	<div class="col-xs-12">
		<p>
			<b>Order ID:</b> {{ $order->id }}
		</p>
		<p>
			<b>Order Time:</b> {{ date('m/d/Y g:i A',strtotime($order->created_at)) }}
		</p>
		@if (count($billing_address))
			<p>
				<b>Billed To:</b>
			<p>
			@include('products::orders.address', array('address'=>$billing_address))
		@endif
		<p>
			<b>Shipping To:</b>
		</p>
		@include('products::orders.address', array('address'=>$shipping_address))
	</div>
</div>
<hr />
<table width="100%" class="table-striped" cellpadding="5">
	<thead>
		<tr>
			<th>Item</th>
			<th></th>
			<th>Price</th>
			<th style="text-align:center;">Quantity</th>
			<th>Subtotal</th>
		</tr>
	</thead>
	<tbody>
@foreach ($Cart->all() as $key => $item)
	<?php $product = json_decode($item['product']); ?>
		<tr>
			<td valign="top" width="150" class="cart-item-image">
				@if (isset($product->images) && count($product->images))
				<a href="/products/{{ $product->slug }}">
					<img src="{{ ($product->images[0]->thumb ? $product->images[0]->thumb : $product->images[0]->image) }}"/>
				</a>
				@endif
			</td>
			<td valign="top" class="cart-item-info">
				<h3 class="cart-item-name">
					<a href="{{ url('products/' . $product->slug) }}">
						{{ $product->name }}
					</a>
				</h3>
				<div class="cart-item-options">
				@foreach($TempCart->getOptions($key) as $group_name => $option)
					<p>
						<em>{{ $group_name }}:</em>
						{{ $option->name }}
					</p>
				@endforeach
				</div>
			</td>
			<td valign="top" width="15%" class="cart-item-price">
				<h3>${{ number_format($item['price'], 2) }}</h3>
			</td>
			<td valign="top" width="10%" align="center" class="cart-item-quantity">
				<h3>{{ $item['qty'] }}</h3>
			</td>
			<td valign="top" width="15%" class="cart-item-subtotal">
				<h3>${{ number_format($TempCart->totalForKey($key),2) }}</h3>
			</td>
		</tr>
@endforeach
@if($shipping = $Cart->totalShipping()) 
		<tr>
			<td colspan="4" align="right"><h3>Shipping:</h3></td>
			<td colspan="2"><h3>${{ number_format($shipping, 2) }}</span></h3></td>
		</tr>
@endif
@if($tax = $Cart->totalTax()) 
		<tr>
			<td colspan="4" align="right"><h3>Tax:</h3></td>
			<td colspan="2"><h3>${{ number_format($tax, 2) }}</span></h3></td>
		</tr>
@endif
@if($discount = $Cart->totalDiscount()) 
		<tr>
			<td colspan="4" align="right"><h3>Discount:</h3></td>
			<td colspan="2"><h3>-${{ number_format($discount, 2) }}</span></h3></td>
		</tr>
@endif
		<tr>
			<td colspan="4" align="right"><h3>Total:</h3></td>
			<td colspan="2"><h3>${{ number_format($order->total, 2) }}</span></h3></td>
		</tr>
	 </tbody>
</table>