@extends('core::admin.template')

@section('title', 'Promo Codes')

@section('js')
@stop

@section('content')
	<div class="row pad">
		<h1>Promo Codes</h1>
		<a class="btn btn-sm btn-primary" href="{{ admin_url('discounts/add') }}">
			<span class="glyphicon glyphicon-plus"></span>
			Add
		</a>
	</div>
	<div class="row text-center">
		{{ $links }}
	</div>

	<div class="row">
		<div class="col-sm-9">
			<table class="table table-striped">
				<thead>
					<tr>
						<th style="width:40px;"></th>
						<th>Name</th>
						<th>Code</th>
						<th>Rate</th>
					</tr>
				</thead>
				<tbody>
				@if($discounts->count())
					@foreach ($discounts as $discount)
					<tr>
						<td>
							<a href="{{ $discount->link_edit() }}" class="btn btn-xs btn-default">
								<span class="glyphicon glyphicon-edit"></span>
							</a>
						</td>
						<td>{{ $discount->name }}</td>
						<td>{{ $discount->code }}</td>
						<td>{{ ($discount->type == "flat" ? "$" : "").number_format($discount->rate,2).($discount->type == "percent" ? "%" : "") }}</td>
					</tr>
					@endforeach
				@else
					<tr>
						<td style="padding:30px;" align="center" colspan="20">
							No Promo Codes Found
						</td>
					</tr>
				@endif
				</tbody>
			</table>
		</div>
	</div>
	<div class="row text-center">
		{{ $links }}
	</div>
@stop