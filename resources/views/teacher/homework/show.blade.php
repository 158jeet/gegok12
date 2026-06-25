@extends('layouts.teacher.layout')

@section('content')
	<div class="">
	    <div id="student_homework_list"></div>
	    @include('partials.message')
	    <show-homework url="{{ url('/') }}" id="{{ $homework->id }}" mode="teacher"></show-homework>  
	</div>
@endsection