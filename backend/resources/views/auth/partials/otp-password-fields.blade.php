@php($minLength = app(\App\Support\Settings::class)->int('auth.password_min_length'))
<div class="mb-3">
    <label for="otp" class="form-label required">OTP</label>
    <input id="otp" name="otp" required inputmode="numeric" autocomplete="one-time-code" maxlength="8"
           class="form-control form-control-lg text-center @error('otp') is-invalid @enderror" autofocus>
</div>
<div class="mb-3">
    <label for="password" class="form-label required">New password</label>
    <input id="password" type="password" name="password" required autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
    <div class="form-text">At least {{ $minLength }} characters with upper- and lower-case letters, a number and a symbol.</div>
</div>
<div class="mb-4">
    <label for="password_confirmation" class="form-label required">Confirm password</label>
    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="form-control">
</div>
