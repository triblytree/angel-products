@extends('core::template')

@section('title', 'Checkout')

@section('meta')
@stop

@section('css')
	<style>
		.required {
			color: #ff0000;
		}
	</style>
@stop

@section('js')
	{{ HTML::script('https://js.stripe.com/v2/') }}
	<script type="text/javascript">
		// This identifies your website in the createToken call below
	 	Stripe.setPublishableKey("{{ Config::get('products::stripe.' . $settings['stripe']['value'] . '.publishable') }}");

		$(function() {
			$('#submit').click(function() {
				@if(Config::get('products::method') == "stripe")   
				$(this).attr('value','Submitting...');
				Stripe.card.createToken($('#payment-form'), stripeResponseHandler);
				@else
				$('#checkout-form').submit();
				$(this).attr('value','Submitting...');
				@endif
			});
		});

		function stripeResponseHandler(status, response) {
			if (response.error) {
				// Show the errors on the form
				$('#address-errors').html('');
				doError($('#payment-errors'), response.error.message);
				return;
			}
			$('#payment-errors').html('');

			var token = response.id;
			$('#stripeToken').val(token);
			$.post('{{ url('checkout') }}', $('#address-form').serialize(), function(data) {
				if (data == 'inventory_fail') {
					doError($('#address-errors'), 'Not enough inventory!');
					window.location = '{{ url('inventory-fail') }}';
					return;
				}
				if (data != 1) {
					doError($('#address-errors'), data);
					console.log(data);
					return;
				}
				window.location = '{{ url('order-summary') }}';
			}).fail(function() {
				doError($('#address-errors'), 'We cannot process your payment at this time.');
			});
		};

		function doError($where, error) {
			$where.html('<div class="alert alert-danger">'+error+'</div>');
			$('#submit').prop('disabled', false).html('Submit Payment');
			$('html, body').stop().animate({
				scrollTop: $where.offset().top - 50
			}, 500);
		}
		
		// Highlight
		jQuery.fn.highlight = function() {
		   $(this).each(function() {
				var el = $(this);
				el.before("<div/>")
				el.prev()
					.width(el.outerWidth())
					.height(el.outerHeight())
					.css({
						"position": "absolute",
						"background-color": "#ffff99",
						"opacity": ".9"   
					})
					.fadeOut(1200);
			});
		}
		
		// Discount
		function discountApply() {
			var $error = $('#discount-errors');
			$error.hide().html('');
			var discount = $('#discount').val();
			if(!discount) $error.show().html("Please enter the promo code you'd like to use.");
			else {
				$.ajax({
					type:'POST',
					url:'/discounts/apply',
					data:{
						code:discount
					},
					dataType:'json',
					success:function(result) {
						console.log(result);
						if(result.error) $('#discount-errors').show().html(result.error);
						else {
							$('#discount').val('');
							$('#discountContainer').show();
							$('#discountDisplay').html(result.discount);
							$('#totalDisplay').html(result.total);
							$('html, body').animate({
								scrollTop: ($("#discountContainer").offset().top - 50)
							}, 200);
							$('.discountContainer').highlight();
						}
					}
				});
			}
		}
		
		@if($rates = Config::get('products::tax'))
		// Tax
		$('input[name="shipping_state"], input[name="billing_state"], input[name="sameAddress"]').change(function() {
			var shipping = $('input[name="shipping_state"]').val();
			var billing_same = $('input[name="sameAddress"]:checked').val();
			if(billing_same) var billing = shipping;
			else var billing = $('input[name="billing_state"]').val();
			if(shipping && billing) {
				$("input[type=submit]").attr('disabled','disabled');
				$.ajax({
					method:'POST',
					url:'/checkout/tax',
					data:{
						shipping:shipping,
						billing:billing
					},
					dataType:'json',
					success:function(result) {
						console.log(result);
						$('#tax').html(result.tax);
						$('#total').html(result.total);
						if(result.tax > 0) {
							$('#taxContainer').show();
							$('html, body').animate({
								scrollTop: ($("#total").offset().top - 25)
							}, 200);
							$('.subtotal').highlight();
						}
						else $('#taxContainer').hide();
						$("input[type=submit]").removeAttr('disabled');
					}
				});
			}
		});
		@endif
	</script>
@stop

@section('content')
		<div class="row">
			<div class="col-sm-4">
				<div class="well">
					<h3>Order Summary</h3>
					<hr />
					<table class="table table-striped">
						<thead>
							<tr>
								<th>Product</th>
								<th>Qty</th>
								<th>Price</th>
							</tr>
						</thead>
						<tbody>
						@foreach ($Cart->all() as $key=>$item)
							<tr>
								<td>
									<?php
										$product = json_decode($item['product']);
										echo $product->name;
										$options = $Cart->getOptions($key);
										if (count($options)) {
											echo ' (';
											$i = 0;
											foreach ($options as $group_name=>$option) {
												echo $group_name . ': ' . $option->name;
												if (++$i < count($options)) echo ', ';
											}
											echo ')';
										}
									?>
								</td>
								<td>
									{{$item['qty'] }} x
								</td>
								<td>
									${{ number_format($item['price'], 2) }}
								</td>
							</tr>
						@endforeach
							{{--
							<tr>
								<td>Taxes</td>
								<td></td>
								<td>$0.00</td>
							</tr>
							--}}
							@if($Cart->totalShipping())
							<tr>
								<td>Shipping</td>
								<td></td>
								<td>${{ number_format($Cart->totalShipping(),2) }}</td>
							</tr>
							@endif
							<tr id="taxContainer"{{ ($Cart->totalTax() ? '' : ' style="display:none;"') }}>
								<td>Tax</td>
								<td></td>
								<td>$<span id="tax">{{ number_format($Cart->totalTax(),2) }}</span></td>
							</tr>
							<tr id="discountContainer"{{ ($Cart->totalDiscount() ? '' : ' style="display:none;"') }}>
								<td>Discount</td>
								<td></td>
								<td>-$<span id="discountDisplay">{{ number_format($Cart->totalDiscount(),2) }}</span></td>
							</tr>
							<tr>
								<td><b>Total</b></td>
								<td></td>
								<td><b>$<span id="totalDisplay">{{ number_format($Cart->total(), 2) }}</span></b></td>
							</tr>
						</tbody>
					</table>
					<a href="{{ url('cart') }}" class="btn btn-default">
						<span class="glyphicon glyphicon-arrow-left"></span>
						Back To Cart
					</a>
				</div>
			</div>
			<div class="col-sm-8">
				<form url="" method="POST" id="checkout-form">
			@if ($settings['stripe']['value'] == 'test')
					<div class="alert alert-info">
						Payments are in test mode.
					</div>
			@endif
					<h1>Checkout</h1>
					<hr />
					<div id="payment-errors"></div>
					<div id="payment-form">
						<div class="form-group" style="max-width:400px;">
							<span class="required">*</span>
							{{ Form::label('card_number', 'Card Number') }}
							{{ Form::text('card_number', null, array('id'=>'card', 'class'=>'form-control', 'placeholder'=>'Card Number', 'data-stripe'=>'number', 'required')) }}
						</div>
						<div class="form-group" style="width:100px;display:inline-block;">
							<span class="required">*</span>
							{{ Form::label('card_expiration_month', 'Exp. Month') }}
							{{ Form::text('card_expiration_month', null, array('class'=>'form-control', 'placeholder'=>'Exp. Month', 'data-stripe'=>'exp_month', 'required')) }}
						</div>
						<div class="form-group" style="width:100px;margin-left:15px;display:inline-block;">
							<span class="required">*</span>
							{{ Form::label('card_expiration_yearh', 'Exp. Year') }}
							{{ Form::text('card_expiration_yearh', null, array('class'=>'form-control', 'placeholder'=>'Exp. Year', 'data-stripe'=>'exp_year', 'required')) }}
						</div>
						<div class="form-group" style="width:100px;">
							<span class="required">*</span>
							{{ Form::label('card_code', 'CVC') }}
							{{ Form::text('card_code', null, array('class'=>'form-control', 'placeholder'=>'CVC', 'data-stripe'=>'cvc', 'required')) }}
						</div>
					</div>
					
					<div id="address-errors"></div>
					{{ Form::token() }}
					<input type="hidden" id="stripeToken" name="stripeToken" />
					<div class="form-group">
						<span class="required">*</span>
						{{ Form::label('email', 'Email') }}
						{{ Form::text('email', (Auth::check()) ? Auth::user()->email : null, array('class'=>'form-control', 'placeholder'=>'Email', 'required')) }}
					</div><gr />
					<h3>Billing Address{{ (Config::get('products::method') == "stripe" ? " (Optional)" : "") }}</h3>
					<hr />
					<div class="form-group">
						{{ Form::label('billing_name', 'Name') }}
						{{ Form::text('billing_name', null, array('class'=>'form-control', 'placeholder'=>'Name', 'required')) }}
					</div>
					<div class="form-group">
						{{ Form::label('billing_address', 'Address') }}
						{{ Form::text('billing_address', null, array('class'=>'form-control', 'placeholder'=>'Address', 'required')) }}
					</div>
					<div class="form-group">
						{{ Form::label('billing_address_2', 'Address 2') }}
						{{ Form::text('billing_address_2', null, array('class'=>'form-control', 'placeholder'=>'Address 2', 'required')) }}
					</div>
					<div class="form-group">
						{{ Form::label('billing_city', 'City') }}
						{{ Form::text('billing_city', null, array('class'=>'form-control', 'placeholder'=>'City', 'required')) }}
					</div>
					<div class="form-group" style="width:70px;">
						{{ Form::label('billing_state', 'State') }}
						{{ Form::text('billing_state', null, array('class'=>'form-control', 'placeholder'=>'State', 'required')) }}
					</div>
					<div class="form-group" style="width:120px;">
						{{ Form::label('billing_zip', 'Zip Code') }}
						{{ Form::text('billing_zip', null, array('class'=>'form-control', 'placeholder'=>'Zip Code', 'required')) }}
					</div><br />
					
					<h3>Shipping Address</h3>
					<hr />
					<div class="form-group">
						<span class="required">*</span>
						{{ Form::label('shipping_name', 'Name') }}
						{{ Form::text('shipping_name', null, array('class'=>'form-control', 'placeholder'=>'Name', 'required')) }}
					</div>
					<div class="form-group">
						<span class="required">*</span>
						{{ Form::label('shipping_address', 'Address') }}
						{{ Form::text('shipping_address', null, array('class'=>'form-control', 'placeholder'=>'Address', 'required')) }}
					</div>
					<div class="form-group">
						{{ Form::label('shipping_address_2', 'Address 2') }}
						{{ Form::text('shipping_address_2', null, array('class'=>'form-control', 'placeholder'=>'Address 2', 'required')) }}
					</div>
					<div class="form-group">
						<span class="required">*</span>
						{{ Form::label('shipping_city', 'City') }}
						{{ Form::text('shipping_city', null, array('class'=>'form-control', 'placeholder'=>'City', 'required')) }}
					</div>
					<div class="form-group" style="width:70px;">
						<span class="required">*</span>
						{{ Form::label('shipping_state', 'State') }}
						{{ Form::text('shipping_state', null, array('class'=>'form-control', 'placeholder'=>'State', 'required')) }}
					</div>
					<div class="form-group" style="width:120px;">
						<span class="required">*</span>
						{{ Form::label('shipping_zip', 'Zip Code') }}
						{{ Form::text('shipping_zip', null, array('class'=>'form-control', 'placeholder'=>'Zip Code', 'required')) }}
					</div><br />
				
				<?php
				$discounts = DB::table('discounts')->count();
				?>
				@if($discounts)
					<h3>Promo Code</h3>
					<hr />
					<div id="discount-errors" class="alert-box alert radius" style="display:none;"></div>
					<div class="form-group">
						{{ Form::text(null, null, array('id'=>'discount', 'class'=>'form-control', 'placeholder'=>'Promo Code')) }}
						<input type="submit" value="Apply" onclick="discountApply();return false;" class="btn discountSubmit" />
					</div><br />
				@endif
				
					<button type="button" class="button btn btn-primary" id="submit" autocomplete="off" style="margin-bottom:15px;">
						Submit Payment
					</button>
				</form>
			</div>
		</div>
@stop