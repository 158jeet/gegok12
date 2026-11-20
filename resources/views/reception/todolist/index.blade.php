@extends('layouts.reception.layout')

@section('content')
    <div id="add_todolist"></div>
    <list-todo url="{{ url('/') }}" mode="receptionist" hidecolumns="true"></list-todo>
    <!-- <portal-target name="list_todolist"></portal-target> -->
@endsection