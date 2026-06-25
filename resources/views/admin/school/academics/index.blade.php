@extends('layouts.admin.layout')

@section('content')
    <div class="relative">
        <div id="add_academic_year"></div>
        @include('partials.message')
        <list-academic-year url="{{ url('/') }}"></list-academic-year>
    </div>
@endsection