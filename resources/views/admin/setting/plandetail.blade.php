@extends('layouts.admin.layout')
@section('content')
    <div class="relative">
        <livewire:admin.setting.plan-detail  :id="$id" />
    </div>
@endsection