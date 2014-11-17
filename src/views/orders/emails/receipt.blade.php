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
<p>
	<b>Order ID:</b>
</p>
<p>
	{{ $order->id }}
</p>
<p>
	<b>Order Time:</b>
</p>
<p>
	{{ $order->created_at }}
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