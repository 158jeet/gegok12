@extends('layouts.teacher.layout')

@section('content')
  <div id="approve_lesson_plan"></div>
  @include('partials.message')
  <list-tab-lesson url="{{ url('/') }}" role="{{ $role }}"></list-tab-lesson>
  <div id="list_lessonplan"></div>
@endsection