<?php
$cart             = json_decode($order->cart, true);
$billing_address  = json_decode($order->billing_address);
$shipping_address = json_decode($order->shipping_address);

$Cart = App::make('Cart');
$Cart->load($cart);
?>
@if($admin)
<p>
	A new order has been placed.
</p>
<p>
	<a href="{{ url("admin/orders/show/".$order->id) }}">{{ url("admin/orders/show/".$order->id) }}</a>
</p>
<p>
	Below is a copy of the receipt.
</p>
@else
<p>
	Thank you for your order!  Here is your receipt.
</p>
@endif

<table cellpadding="4">
	<tr>
		<td valign="top"><b>Order ID:</b></td>
		<td valign="top">{{ $order->id }}</td>
	</tr>
	<tr>
		<td valign="top"><b>Order Time:</b></td>
		<td valign="top">{{ date('m/d/Y g:i A',strtotime($order->created_at)) }}</td>
	</tr>
	<tr>
		<td valign="top"><b>E-mail:</b></td>
		<td valign="top"><a href="mailto:{{ $order->email }}">{{ $order->email }}</a></td>
	</tr>
	<tr>
		<td valign="top"><b>Shipping:</b></td>
		<td valign="top">
			@include('products::orders.address', array('address'=>$shipping_address))
		</td>
	</tr>
	@if (count($billing_address))
	<tr>
		<td valign="top"><b>Billing:</b></td>
		<td valign="top">
			@include('products::orders.address', array('address'=>$billing_address))
		</td>
	</tr>
	@endif
</table>
<hr />
@foreach ($Cart->all() as $key=>$item)
	<?php $product = json_decode($item['product']); ?>
	@if (isset($product->images) && count($product->images))
		<p>
			<img src="{{ $message->embed(public_path() . $product->images[0]->image) }}" style="width:240px" width="240" />
		</p>
	@endif
	<p>
		<a href="{{ url('products/' . $product->slug) }}">
			{{ $product->name }}
		</a>
		<?php
		$options = $Cart->getOptions($key);
		if (count($options)) {
			echo '(';
			$i = 0;
			foreach ($options as $group_name=>$option) {
				echo $group_name . ': ' . $option->name;
				if (++$i < count($options)) echo ', ';
			}
			echo ')';
		}
		?>
		-
		${{ number_format($item['price'], 2) }}
		x
		{{ $item['qty'] }}
	</p>
	<hr />
@endforeach
@if($shipping = $Cart->totalShipping()) 
<p class="text-right">
	<b>Shipping: ${{ number_format($shipping, 2) }}</b>
</p>
@endif
@if($tax = $Cart->totalTax()) 
<p class="text-right">
	<b>Tax: ${{ number_format($tax, 2) }}</b>
</p>
@endif
@if($discount = $Cart->totalDiscount()) 
<p class="text-right">
	<b>Discount: -${{ number_format($discount, 2) }}</b>
</p>
@endif
<p class="text-right">
	<b>Total: ${{ number_format($order->total, 2) }}</b>
</p>