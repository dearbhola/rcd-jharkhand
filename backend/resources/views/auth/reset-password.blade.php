<x-guest-layout title="Reset password">
    <form method="POST" action="{{ route('password.reset.update') }}" novalidate>
        @csrf
        @include('auth.partials.otp-password-fields')
        <button class="btn btn-primary w-100 py-2"><i class="bi bi-key me-1"></i> Reset password</button>
    </form>
    <div class="text-center small mt-4"><a href="{{ route('password.forgot') }}">Resend OTP</a></div>
</x-guest-layout>
