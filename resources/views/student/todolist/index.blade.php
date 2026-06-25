@extends('layouts.student.layout')

@section('content')
    <div id="add_todolist"></div>
    <list-todo url="{{ url('/') }}" mode="student" hidecolumns="true"></list-todo>
    <!-- <portal-target name="list_todolist"></portal-target> -->
@endsection