<x-guest-layout title="Citizen registration">
    <p class="small text-muted">Register to report road damage near you. We will send a one-time password (OTP) to your mobile.</p>
    <form method="POST" action="{{ route('register.send-otp') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="name" class="form-label required">Full name</label>
            <input id="name" name="name" value="{{ old('name') }}" required maxlength="100" class="form-control @error('name') is-invalid @enderror">
        </div>
        <div class="mb-4">
            <label for="mobile" class="form-label required">Mobile number</label>
            <div class="input-group">
                <span class="input-group-text">+91</span>
                <input id="mobile" name="mobile" value="{{ old('mobile') }}" required inputmode="numeric" maxlength="10" pattern="[6-9][0-9]{9}"
                       class="form-control @error('mobile') is-invalid @enderror" autocomplete="tel-national">
            </div>
        </div>
        <button class="btn btn-primary w-100 py-2"><i class="bi bi-send me-1"></i> Send OTP</button>
    </form>
    <div class="text-center small mt-4"><a href="{{ route('login') }}">Already registered? Sign in</a></div>
</x-guest-layout>
