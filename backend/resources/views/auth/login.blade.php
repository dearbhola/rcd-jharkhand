@php($otpEnabled = app(\App\Support\Settings::class)->bool('auth.sms_otp_enabled'))
<x-guest-layout title="Sign in">
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="identifier" class="form-label">Email, mobile or employee code</label>
            <input id="identifier" name="identifier" value="{{ old('identifier') }}" required autofocus autocomplete="username"
                   class="form-control @error('identifier') is-invalid @enderror">
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="form-control">
        </div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                <label class="form-check-label small" for="remember">Keep me signed in</label>
            </div>
            @if ($otpEnabled)
                <a href="{{ route('password.forgot') }}" class="small">Forgot password?</a>
            @endif
        </div>
        <button class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-1"></i> Sign in</button>
    </form>
    <hr class="my-4">
    <div class="text-center small text-muted">
        @if ($otpEnabled)
            Citizen? <a href="{{ route('register') }}">Register with your mobile number</a>
        @else
            Forgot your password or need an account? Contact your RCD office administrator.
        @endif
    </div>
</x-guest-layout>
