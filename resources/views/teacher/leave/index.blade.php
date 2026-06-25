@extends('layouts.teacher.layout')

@section('content')
  	<div id="approve_leave"></div>
  	@include('partials.message')
  	@if($user_type == 'apply')
    	<leave-teacher-list url="{{ url('/') }}" type="{{ $user_type }}"></leave-teacher-list>
  	@elseif($user_type == 'check')
    	<leave-teacher-list url="{{ url('/') }}" type="{{ $user_type }}" searchquery="{{ $query }}"></leave-teacher-list>
  	@endif
@endsection