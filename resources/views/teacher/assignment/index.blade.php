@extends('layouts.teacher.layout')

@section('content')
    <div id="approve_assignment"></div>
    @include('partials.message')
    <list-tab-assignment url="{{ url('/') }}" role="{{ $role }}" type="teacher" scope="" hidecolumns="false" searchquery="{{ $query }}"></list-tab-assignment>
    <div id="list_assignment"></div>
@endsection