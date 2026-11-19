@extends('layouts.admin.layout')

@section('content')
   	<div id="add_notice"></div>
   	<notice-board-list url="{{ url('/') }}" scope="" hidecolumns="false" searchquery="{{ $query }}" mode="admin"></notice-board-list>
@endsection