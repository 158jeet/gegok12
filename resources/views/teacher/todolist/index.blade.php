@extends('layouts.teacher.layout')

@section('content')
    <div id="add_todolist"></div>
    <list-todo url="{{ url('/') }}" mode="teacher" hidecolumns="false"></list-todo>
    <!-- <portal-target name="list_todolist"></portal-target> -->
@endsection