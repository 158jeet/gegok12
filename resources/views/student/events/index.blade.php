@extends('layouts.student.layout')

@section('content')
    <div class="py-5">
        <show-event url="{{url('/')}}" count="{{ $count }}" no_of_events="{{ $subscription->plan->no_of_events }}" events="{{ $events }}"></show-event>
    </div>
    <event-popup :url="this.url" mode="student"></event-popup>
    <div id="eventpopup"></div>
@endsection