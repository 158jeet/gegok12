@extends('layouts.accountant.layout')

@section('content')
    <div id="add_notice"></div>
    <notice-board-list url="{{ url('/') }}" scope="" hidecolumns="true" searchquery="{{ $query }}" mode="accountant"></notice-board-list>
@endsection