@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="{{ asset('tagore-erp.css') }}">
<div class="tg-app"><div class="tg-shell">
<header class="tg-topbar"><div class="tg-brand"><div class="tg-logo">T</div><div><strong>TagoreK12</strong><span>Fee Wallet</span></div></div><a class="tg-btn" href="{{ route('tagore.dashboard') }}">ERP Home</a></header>
@if(session('success'))<div class="tg-alert success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="tg-alert danger">{{ $errors->first() }}</div>@endif
<section class="tg-card"><h2>Wallets</h2><div class="tg-table-wrap"><table><thead><tr><th>Student</th><th>Institution</th><th>Balance</th><th>Action</th></tr></thead><tbody>@foreach($wallets as $w)<tr><td>{{ $w->student_id }}</td><td>{{ $w->institution_id }}</td><td>₹{{ number_format($w->balance,2) }}</td><td>@if($w->balance>0)<form method="POST" action="{{ route('tagore.fees.wallet.pay') }}" style="display:inline">@csrf<input type="hidden" name="student_id" value="{{ $w->student_id }}"><input type="hidden" name="institution_id" value="{{ $w->institution_id }}"><input name="amount" type="number" min="0.01" max="{{ $w->balance }}" step="0.01" placeholder="Amount" required><button class="tg-btn primary">Pay fee</button></form>@endif</td></tr>@endforeach</tbody></table></div></section>
</div></div>
@endsection
