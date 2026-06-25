@extends('layouts.admin.layout')

@section('content')
    <div class="relative">
        @include('partials.message')
        <list-phone-number url="{{ url('/') }}" mode="admin"></list-phone-number>
   </div>
@endsection