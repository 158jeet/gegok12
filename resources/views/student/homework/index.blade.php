@extends('layouts.student.layout')

@section('content')
   	<div id="add_homework"></div>
   	<homework-list url="{{ url('/') }}" scope="" hidecolumns="true" searchquery="{{ $query }}" mode="student"></homework-list>
@endsection