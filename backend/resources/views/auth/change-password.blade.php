@php($minLength = app(\App\Support\Settings::class)->int('auth.password_min_length'))
<x-app-layout title="Change password" :breadcrumbs="['Change password']">
    <x-page-header title="Change password" />
    <div class="row">
        <div class="col-lg-6">
            @if ($forced)
                <div class="alert alert-warning small"><i class="bi bi-shield-exclamation me-1"></i>
                    Your password was set by an administrator or has expired. Choose a new one to continue.</div>
            @endif
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('password.change.update') }}" novalidate>
                        @csrf @method('PUT')
                        <div class="mb-3">
                            <label for="current_password" class="form-label required">Current password</label>
                            <input id="current_password" type="password" name="current_password" required autocomplete="current-password"
                                   class="form-control @error('current_password') is-invalid @enderror">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label required">New password</label>
                            <input id="password" type="password" name="password" required autocomplete="new-password"
                                   class="form-control @error('password') is-invalid @enderror">
                            <div class="form-text">At least {{ $minLength }} characters with upper- and lower-case letters, a number and a symbol.</div>
                        </div>
                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label required">Confirm new password</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="form-control">
                        </div>
                        <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Update password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
