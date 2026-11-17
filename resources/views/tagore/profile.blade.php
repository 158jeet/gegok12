<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My profile · Tagore ERP</title>
<link rel="stylesheet" href="{{ asset('tagore-erp.css') . '?v=20261029-ui10' }}">
<style>
.tg-profile-page{max-width:980px;margin:28px auto;padding:0 22px 48px;color:#17212b}.tg-profile-head{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:22px}.tg-profile-head h1{font-size:28px;margin:0}.tg-profile-head p{margin:6px 0 0;color:#687785}.tg-profile-back{color:#176b53;text-decoration:none;font-weight:700}.tg-profile-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.tg-profile-card{background:#fff;border:1px solid #dce4e9;border-radius:16px;padding:22px;box-shadow:0 8px 24px rgba(15,23,42,.05)}.tg-profile-card h2{font-size:18px;margin:0 0 5px}.tg-profile-card>p{color:#687785;font-size:13px;margin:0 0 18px}.tg-profile-field{display:block;margin:0 0 15px}.tg-profile-field span{display:block;font-size:13px;font-weight:700;margin-bottom:6px}.tg-profile-field input{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #cbd5dc;border-radius:9px;font:inherit}.tg-profile-field input[readonly]{background:#f3f6f8;color:#65727d}.tg-profile-submit{border:0;border-radius:9px;padding:11px 16px;background:#176b53;color:white;font-weight:700;cursor:pointer}.tg-profile-notice{padding:12px 14px;margin-bottom:14px;border-radius:10px;background:#eaf7f0;color:#176b43}.tg-profile-error{color:#b42318;font-size:12px;margin-top:5px}.tg-profile-role{display:inline-block;margin-top:8px;padding:5px 9px;border-radius:20px;background:#eef3f6;color:#425466;font-size:12px}@media(max-width:700px){.tg-profile-grid{grid-template-columns:1fr}.tg-profile-head{align-items:flex-start;flex-direction:column}.tg-profile-page{padding:0 14px 36px}}
</style>
</head>
<body class="tg-app">
<main class="tg-profile-page">
  <header class="tg-profile-head">
    <div><h1>My profile & settings</h1><p>Manage your own contact details and account password.</p></div>
    <a class="tg-profile-back" href="{{ route('tagore.dashboard') }}">← Back to dashboard</a>
  </header>
  @if(session('profile_status'))<div class="tg-profile-notice" role="status">{{ session('profile_status') }}</div>@endif
  @if(session('password_status'))<div class="tg-profile-notice" role="status">{{ session('password_status') }}</div>@endif
  <div class="tg-profile-grid">
    <section class="tg-profile-card">
      <h2>Personal details</h2><p>These changes apply only to your signed-in account.</p>
      <form method="POST" action="{{ route('tagore.profile.update') }}">
        @csrf @method('PATCH')
        <label class="tg-profile-field"><span>Full name</span><input name="name" value="{{ old('name', $user->name) }}" maxlength="255" required>@error('name')<small class="tg-profile-error">{{ $message }}</small>@enderror</label>
        <label class="tg-profile-field"><span>Mobile number</span><input name="mobile_no" value="{{ old('mobile_no', $user->mobile_no) }}" maxlength="25" inputmode="tel">@error('mobile_no')<small class="tg-profile-error">{{ $message }}</small>@enderror</label>
        <label class="tg-profile-field"><span>Email address</span><input value="{{ $user->email }}" readonly aria-describedby="email-help"><small id="email-help" style="color:#687785;font-size:12px">Email changes require a separate verification process.</small></label>
        <div><strong>Account role</strong><div class="tg-profile-role">{{ optional($user->usergroup)->name ?? 'Tagore ERP user' }}</div></div>
        <p style="margin-top:18px"><button class="tg-profile-submit" type="submit">Save profile</button></p>
      </form>
    </section>
    <section class="tg-profile-card">
      <h2>Change password</h2><p>Use your current password to set a new password.</p>
      <form method="POST" action="{{ route('tagore.profile.password') }}">
        @csrf @method('PUT')
        <label class="tg-profile-field"><span>Current password</span><input type="password" name="current_password" autocomplete="current-password" required>@error('current_password')<small class="tg-profile-error">{{ $message }}</small>@enderror</label>
        <label class="tg-profile-field"><span>New password</span><input type="password" name="password" minlength="10" autocomplete="new-password" required>@error('password')<small class="tg-profile-error">{{ $message }}</small>@enderror</label>
        <label class="tg-profile-field"><span>Confirm new password</span><input type="password" name="password_confirmation" minlength="10" autocomplete="new-password" required></label>
        <button class="tg-profile-submit" type="submit">Update password</button>
      </form>
    </section>
  </div>
</main>
</body>
</html>
