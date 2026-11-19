@extends('layouts.teacher.layout')

@section('content')
   <div id="assignment_list"></div>
   <student-assignment-list url="{{ url('/') }}" id="{{ $assignment->id }}" searchquery="{{ $query }}" viewers="{{count($assignment->viewers)}}"></student-assignment-list>
@endsection