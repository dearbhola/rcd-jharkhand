<x-guest-layout title="Forgot password">
    <p class="small text-muted">Enter your registered mobile number. We will send an OTP to reset your password.</p>
    <form method="POST" action="{{ route('password.forgot.send') }}" novalidate>
        @csrf
        <div class="mb-4">
            <label for="mobile" class="form-label required">Mobile number</label>
            <div class="input-group">
                <span class="input-group-text">+91</span>
                <input id="mobile" name="mobile" value="{{ old('mobile') }}" required inputmode="numeric" maxlength="10"
                       class="form-control @error('mobile') is-invalid @enderror" autocomplete="tel-national">
            </div>
        </div>
        <button class="btn btn-primary w-100 py-2"><i class="bi bi-send me-1"></i> Send OTP</button>
    </form>
    <div class="text-center small mt-4"><a href="{{ route('login') }}">Back to sign in</a></div>
</x-guest-layout>
