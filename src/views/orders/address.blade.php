{{ $address->name }}<br />
{{ $address->address }}<br />
@if ($address->address_2)
{{ $address->address_2 }}<br />
@endif
{{ $address->city }}, {{ $address->state }} {{ $address->zip }}<br />
@if ($address->country and $address->country != "United_States")
{{ $address->country }}<br />
@endif       
@if ($address->phone)
{{ $address->phone }}<br />
@endif       