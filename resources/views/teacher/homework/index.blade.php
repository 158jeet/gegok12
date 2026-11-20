@extends('layouts.teacher.layout')

@section('content')
    @if(Auth::user()->school->settings->approval == 'true')
        <div id="add_homework"></div>
        <home-work-list url="{{ url('/') }}" scope="" hidecolumns="false" searchquery="{{ $query }}" mode="teacher"></home-work-list>
    @else
        <div id="add_homework"></div>
        <list-tab-homework url="{{ url('/') }}" scope="" role="{{ $role }}" hidecolumns="false" searchquery="{{ $query }}" mode="teacher"></list-tab-homework>
        <div id="list_homework"></div>
    @endif
@endsection