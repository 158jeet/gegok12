@extends('layouts.reception.layout')

@section('content')
  	<div id="approve_leave"></div>
  	@include('partials.message')
    	<reception-leave-list url="{{ url('/') }}" type="{{ $user_type }}"></reception-leave-list>
@endsection