@extends('layouts.student.layout')

@section('content')
   	<div id="add_notice"></div>
   	<notice-board-list url="{{ url('/') }}" scope="" hidecolumns="true" searchquery="{{ $query }}" mode="student"></notice-board-list>
@endsection