@extends('core::template')

@section('title', $category->name)

@section('css')
@stop

@section('js')
@stop

@section('content')
<main class="product-category">
	<div class="row">
		<h1>{{ $category->name }}</h1>
	</div>
	
	@if(count($category->children) > 0)
	<div class="row category-subcategories">
		@foreach($category->children as $subcategory)
		<div class="col-sm-12 col-md-6 col-lg-4">
			<a href="{{ $subcategory->link() }}">
				<span class="category-subcategory-image">
					@if($image = $subcategory->image())
					<img src="{{ asset($image) }}" alt="{{{ $subcategory->name }}}">
					@endif
				</span>
				<h3 class="category-subcategory-name">{{ $subcategory->name }}</h3>
			</a>
		</div>
		@endforeach
	</div>
	@endif
	
	@if(count($category->products) > 0)
	<div class="row category-products">
		@foreach($category->products as $product)
		<div class="col-sm-12 col-md-6 col-lg-4">
			<a href="{{ $product->link() }}">
				<span class="category-product-image">
					@if($image = $product->image())
					<img src="{{ $image }}" alt="{{{ $product->name }}}">
					@endif
				</span><br />
				<span class="category-product-name">{{ $product->name }}</span>
			</a>
		</div>
		@endforeach
	</div>
	@endif
</main>
@stop