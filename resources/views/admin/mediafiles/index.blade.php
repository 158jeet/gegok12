@extends('layouts.admin.layout')
@section('content')
    <div class="w-full relative"> 
        <div id="add_media_file"></div>
        <div class="bg-white my-3">
            @include('partials.message')
            <file-list-tab url="{{ url('/') }}"></file-list-tab>
            <div id="media_file_list"></div>
        </div>
    </div>
@endsection