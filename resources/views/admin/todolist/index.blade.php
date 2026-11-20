@extends('layouts.admin.layout')

@section('content')
    <div id="add_todolist"></div>
    <list-todo url="{{ url('/') }}" mode="admin" hidecolumns="false"></list-todo>
    <!-- <portal-target name="list_todolist"></portal-target> -->
@endsection