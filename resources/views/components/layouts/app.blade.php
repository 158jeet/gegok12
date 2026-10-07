@php
    // Compatibility wrapper for legacy <x-layouts.app> references.
    // The canonical application shell remains resources/views/layouts/app.blade.php.
@endphp
@section('base-content')
    {!! $slot !!}
@endsection
@include('layouts.app')
