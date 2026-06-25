@extends('layouts.reception.layout')

@section('content')
    <div class="relative">
        @include('partials.message')
        <list-phone-number url="{{ url('/') }}" mode="receptionist"></list-phone-number>
   </div>
@endsection