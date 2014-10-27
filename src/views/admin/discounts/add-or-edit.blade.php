@extends('core::admin.template')

@section('title', ucfirst($action).' Promo Code')

@section('css')
@stop

@section('js')
<script type="text/javascript">
$(document).ready(function() {
	toggleType();		
});
function toggleType() {
	var type = $('#type').val();
	if(type) {
		$('.rate_type').hide();
		$('#rate_type_'+type).show();
		$('#rate_row').show();
	}
	else $('#rate_row').hide();
}
</script>
@stop

@section('content')
	<h1>{{ ucfirst($action) }} Promo Code</h1>
	@if ($action == 'edit')
		{{ Form::open(array('role'=>'form',
							'url'=>'admin/discounts/delete/'.$discount->id,
							'class'=>'deleteForm',
							'data-confirm'=>'Delete this promo code forever?  This action cannot be undone!')) }}
			<input type="submit" class="btn btn-sm btn-danger" value="Delete Forever" />
		{{ Form::close() }}
	@endif

	@if ($action == 'edit')
		{{ Form::model($discount, array('role'=>'form', 'id'=>'discountForm')) }}
	@elseif ($action == 'add')
		{{ Form::open(array('role'=>'form', 'id'=>'discountForm')) }}
	@endif

		@if (isset($menu_id))
			{{ Form::hidden('menu_id', $menu_id) }}
		@endif

		<div class="row">
			<div class="col-md-12">
				<table class="table table-striped">
					<tbody>
						<tr>
							<td>
								<span class="required">*</span>
								{{ Form::label('name', 'Name') }}
							</td>
							<td>
								<div style="width:300px">
									{{ Form::text('name', null, array('class'=>'form-control', 'required')) }}
								</div>
							</td>
						</tr>
						<tr>
							<td>
								<span class="required">*</span>
								{{ Form::label('code', 'Code') }}
							</td>
							<td>
								<div style="width:300px">
									{{ Form::text('code', null, array('class'=>'form-control', 'required')) }}
								</div>
							</td>
						</tr>
						<tr>
							<td>
								<span class="required">*</span>
								{{ Form::label('type', 'Type') }}
							</td>
							<td>
								<div style="width:300px">
									{{ Form::select('type', array('' => '','flat' => 'Flat Rate','percent' => 'Percent'), null, array('class'=>'form-control', 'onchange' => 'toggleType();', 'required')) }}
								</div>
							</td>
						</tr>
						<tr id="rate_row" style="display:none;">
							<td>
								<span class="required">*</span>
								{{ Form::label('rate', 'Rate') }}
							</td>
							<td>
								<div style="float:left;display:none;padding:8px 5px 0px 0px;" class="rate_type" id="rate_type_flat">$</div>
								<div style="width:80px;float:left;">
									{{ Form::text('rate', null, array('class'=>'form-control', 'required')) }}
								</div>
								<div style="float:left;display:none;padding:8px 0px 0px 5px;" class="rate_type" id="rate_type_percent">%</div>
							</td>
						</tr>
						<tr>
							<td>
								{{ Form::label('onetime', 'Onetime Use?') }}
							</td>
							<td>
								{{ Form::checkbox('onetime',1) }}
								Yes
							</td>
						</tr>
						<tr>
							<td>
								{{ Form::label('user_id', 'User?') }}
							</td>
							<td>
								<?php
								$options = array('' => '');
								$User = App::make('User');
								$users = $User
									->orderBy('first_name','asc')
									->orderBy('last_name','asc')
									->orderBy('username','asc')
									->get();
								foreach($users as $user) {
									$options[$user->id] = ($user->first_name ? $user->first_name.' '.$user->last_name : $user->username);
								}
								?>
								{{ Form::select('user_id',$options) }}
							</td>
						</tr>
					</tbody>
				</table>
			</div>{{-- Left Column --}}
		</div>{{-- Row --}}
		<div class="text-right pad">
			<input type="submit" class="btn btn-primary" value="Save" id="save" />
		</div>
	{{ Form::close() }}
@stop