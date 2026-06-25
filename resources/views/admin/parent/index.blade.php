@extends('layouts.admin.layout')

@section('content')
    <div class="relative">
    	<div id="parent_index"></div>
        @include('partials.message')
        <parent-list url="{{ url('/') }}" searchquery="{{ $query }}"></parent-list>
    </div>
@endsection