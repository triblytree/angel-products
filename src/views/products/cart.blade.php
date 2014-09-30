@extends('core::template')

@section('title', 'View Cart')

@section('meta')
@stop

@section('css')
	<style>
		.fakePriceWrap {
			text-decoration:line-through;
			font-style:italic;
		}
	</style>
@stop

@section('js')
	{{ HTML::script('packages/angel/core/js/jquery/jquery.throttle-debounce.min.js') }}
	<script>
		$(function() {
			function qtyFormSubmit() {
				$.post($(this).attr('action'), $(this).serialize(), function(data) {
					$('#subtotal').html(data);
					$('#proceed').removeClass('disabled');
				}).fail(function() {
					alert('There was an error connecting to our servers.');
				});
			}

			$('#qtyForm').submit(function(e) {
				e.preventDefault();

				$('#subtotal').html('...');
				$('#proceed').addClass('disabled');
			}).submit($.debounce(500, qtyFormSubmit));

			$('.qty').change(function() {
				var initial = parseInt($(this).val());
				if (!initial) $(this).val(1);
				var max_qty = parseInt($(this).data('max-qty'));
				if ($(this).val() > max_qty) {
					$(this).val(max_qty);
					alert('There are only ' + max_qty + ' of these available for purchase.');
				}
				$('#qtyForm').submit();
			});
			/*.keyup(function() {
				$(this).trigger('change');
			})*/

			$('.qtyPlus').click(function() {
				var $qty = $(this).prev();
				adjustQuantity($qty, 1);
			});
			$('.qtyMinus').click(function() {
				var $qty = $(this).next();
				adjustQuantity($qty, -1);
			});

			function adjustQuantity($qty, by) {
				var qty = $qty.val();
				var qtyNew = parseInt($qty.val()) + by;
				qtyNew = (qtyNew) ? qtyNew : 1;
				if (qty == qtyNew) return;
				$qty.val(qtyNew).trigger('change');
			}

			$('.removeItem').click(function() {
				$(this).addClass('disabled').html('Removing item...');
			});
		});
	</script>
@stop

@section('content')
		<div class="row">
			<h1>Shopping Cart</h1>
		</div>
		<div class="row">
	@if (!$Cart->all())
			<div class="alert alert-info">
				There are no items in your cart!
			</div><br />
	@else
			{{ Form::open(array('id'=>'qtyForm', 'url'=>'cart-qty')) }}
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
		@foreach ($Cart->all() as $key=>$item)
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
					@foreach ($Cart->getOptions($key) as $group_name=>$option)
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
							{{--
							<button type="button" class="btn btn-primary btn-xs qtyMinus">
								<span class="glyphicon glyphicon-minus"></span>
							</button>
							{{ Form::text('qty['.$key.']', $item['qty'], array('class'=>'form-control text-center qty', 'style'=>'display:inline-block;width:50px;', 'data-max-qty'=>isset($item['max_qty']) ? $item['max_qty'] : null)) }}
							<button type="button" class="btn btn-primary btn-xs qtyPlus">
								<span class="glyphicon glyphicon-plus"></span>
							</button>
							--}}
							<h3>{{ $item['qty'] }}</h3>
						</td>
						<td valign="top" width="15%" class="cart-item-subtotal">
							<h3>${{ number_format($Cart->totalForKey($key),2) }}</h3>
						</td>
						<td valign="top" width="5%" class="cart-item-remove">
							<a href="{{ url('cart-remove/' . urlencode($key)) }}" class="glyphicon glyphicon-remove removeItem"></a>
						</td>
					</tr>
		@endforeach
		@if ($Cart->all())
					<tr>
						<td colspan="4" align="right"><h3>Subtotal:</h3></td>
						<td colspan="2"><h3>$<span id="subtotal">{{ number_format($Cart->total(), 2) }}</span></h3></td>
					</tr>
		@endif
				 </tbody>
			</table>
			{{ Form::close() }}<br />
			<a id="proceed" class="button button-right btn btn-primary" href="{{ url('checkout') }}">
				Proceed to Checkout
			</a>
			<div class="clearfix"></div><br />
		</div>
	@endif
@stop