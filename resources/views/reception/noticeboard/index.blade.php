@extends('layouts.reception.layout')

@section('content')
    <div id="add_notice"></div>
   	<notice-board-list url="{{ url('/') }}" scope="" hidecolumns="true" searchquery="{{ $query }}" mode="receptionist"></notice-board-list>
@endsection