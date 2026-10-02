<x-guest-layout title="Verify your mobile">
    <p class="small text-muted">Enter the OTP sent to <strong>{{ $mobile }}</strong> and choose a password.</p>
    <form method="POST" action="{{ route('register.verify.store') }}" novalidate>
        @csrf
        @include('auth.partials.otp-password-fields')
        <button class="btn btn-primary w-100 py-2"><i class="bi bi-check2-circle me-1"></i> Create account</button>
    </form>
    <div class="text-center small mt-4"><a href="{{ route('register') }}">Change number / resend OTP</a></div>
</x-guest-layout>
