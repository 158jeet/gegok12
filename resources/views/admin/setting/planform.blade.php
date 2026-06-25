@extends('layouts.admin.layout')
@section('content')
    <div class="relative">
        <livewire:admin.setting.plan-form  :id="$id" />
    </div>
@endsection