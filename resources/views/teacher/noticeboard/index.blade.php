@extends('layouts.teacher.layout')

@section('content')
   	<div id="add_notice"></div>
   	<notice-board-list url="{{ url('/') }}" scope="" hidecolumns="true" searchquery="{{ $query }}" mode="teacher"></notice-board-list>
@endsection