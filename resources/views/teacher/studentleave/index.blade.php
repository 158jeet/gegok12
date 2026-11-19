@extends('layouts.teacher.layout')

@section('content')
    <div class="flex flex-wrap lg:flex-row  items-center mb-5 justify-between">
        <div class="">
            <h1 class="admin-h1 my-3">Student Leave Applications</h1>
        </div>
    </div>
    @include('partials.message')
    <student-leave-tab url="{{ url('/') }}"></student-leave-tab>
    <div id="student_leave_list"></div>
@endsection