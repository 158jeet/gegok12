@extends('layouts.empty')
@section('content')
<div class="tg-login">
<style>
.tg-login{min-height:100vh;display:grid;grid-template-columns:1.08fr .92fr;background:#f6f8fc;color:#142033;font-family:Inter,ui-sans-serif,system-ui,sans-serif}
.tg-login-left{position:relative;overflow:hidden;background:linear-gradient(145deg,#10244a,#2457b5 65%,#4f7cff);color:#fff;padding:58px;display:flex;flex-direction:column;justify-content:space-between}
.tg-login-left:before,.tg-login-left:after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.07)}.tg-login-left:before{width:420px;height:420px;right:-190px;top:-120px}.tg-login-left:after{width:300px;height:300px;left:-160px;bottom:-100px}
.tg-login-brand{position:relative;z-index:1;display:flex;gap:12px;align-items:center}.tg-login-logo{width:48px;height:48px;border-radius:15px;background:rgba(255,255,255,.15);display:grid;place-items:center;font-size:22px;font-weight:900;border:1px solid rgba(255,255,255,.2)}.tg-login-brand strong{font-size:20px}.tg-login-brand span{display:block;font-size:11px;color:#cddcff;margin-top:2px}
.tg-login-copy{position:relative;z-index:1;max-width:580px}.tg-login-copy h1{font-size:48px;line-height:1.05;letter-spacing:-1.7px;margin:0 0 16px}.tg-login-copy p{font-size:15px;line-height:1.7;color:#d8e4ff;max-width:520px}.tg-login-points{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:28px}.tg-login-point{padding:13px 14px;border-radius:13px;background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.12);font-size:12px}.tg-login-point b{display:block;margin-bottom:4px}.tg-login-foot{position:relative;z-index:1;color:#b8c9ec;font-size:11px}
.tg-login-right{display:flex;align-items:center;justify-content:center;padding:35px}.tg-login-card{width:min(440px,100%);background:#fff;border:1px solid #e5eaf1;border-radius:24px;padding:34px;box-shadow:0 22px 60px rgba(16,24,40,.08)}.tg-login-card h2{font-size:27px;letter-spacing:-.6px;margin:0 0 6px}.tg-login-card>p{color:#667085;font-size:13px;margin:0 0 26px}.tg-login-field{margin:0 0 17px}.tg-login-field label{display:block;font-size:11px;font-weight:800;color:#475467;margin-bottom:7px}.tg-login-field input{width:100%;height:46px;border:1px solid #d7dde7!important;border-radius:11px!important;padding:0 13px!important;font-size:13px;outline:none}.tg-login-field input:focus{border-color:#4f7cff!important;box-shadow:0 0 0 3px rgba(79,124,255,.1)}.tg-login-row{display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#667085;margin:6px 0 22px}.tg-login-row a{color:#2457e6;text-decoration:none;font-weight:700}.tg-login-submit{width:100%;height:46px;border:0!important;border-radius:11px!important;background:linear-gradient(135deg,#2457e6,#4f7cff)!important;color:#fff!important;font-size:13px;font-weight:800;box-shadow:0 9px 20px rgba(36,87,230,.2)}.tg-login-error{background:#fff1f1;border:1px solid #ffd1d1;color:#b83232;border-radius:10px;padding:10px 12px;font-size:12px;margin-bottom:16px}
@media(max-width:800px){.tg-login{grid-template-columns:1fr}.tg-login-left{display:none}.tg-login-right{min-height:100vh;padding:20px}.tg-login-card{padding:26px;border-radius:19px}}
</style>
<div class="tg-login-left">
  <div class="tg-login-brand"><div class="tg-login-logo">T</div><div><strong>Tagore ERP</strong><span>Tagore Group of Institutions</span></div></div>
  <div class="tg-login-copy"><h1>Everything your school needs. In one place.</h1><p>A focused workspace for management, academics, admissions, fees, people and communication — designed around the people who actually use it.</p><div class="tg-login-points"><div class="tg-login-point"><b>Management</b>See what needs attention today.</div><div class="tg-login-point"><b>Academics</b>Keep student progress connected.</div><div class="tg-login-point"><b>Finance</b>Track fees from demand to receipt.</div><div class="tg-login-point"><b>People</b>Turn routine work into clear actions.</div></div></div>
  <div class="tg-login-foot">Secure school management · Tagore Group of Institutions</div>
</div>
<div class="tg-login-right"><div class="tg-login-card">
  <h2>Welcome back</h2><p>Sign in to continue to your Tagore ERP workspace.</p>
  @include('partials.message')
  @if($errors->any())<div class="tg-login-error">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
  @if((int) \App\Models\Setting::where('key', 'login_status')->value('value') !== 1)<div class="tg-login-error">Login is temporarily under maintenance.</div>@else
  <form method="POST" action="{{ route('login') }}" aria-label="{{ __('Login') }}">@csrf
    <div class="tg-login-field"><label for="email">Email / Registration Number</label><input id="email" type="text" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus placeholder="Enter your email or registration number"></div>
    <div class="tg-login-field"><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required placeholder="Enter your password"></div>
    <div class="tg-login-row"><label><input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}> Remember me</label><a href="{{ route('password.request') }}">Forgot password?</a></div>
    <button class="tg-login-submit" type="submit">Sign in to Tagore ERP</button>
  </form>
  @endif
</div></div>
</div>
@endsection