@extends('layouts.guest')

@section('content')
    {{-- MODIFIED: Replaced Bootstrap structure with DaisyUI/Tailwind CSS --}}
    <div class="card w-full bg-base-100">
        <div class="card-body">
            <h2 class="card-title justify-center text-2xl">{{ __('Login') }}</h2>
            
            <form method="POST" action="{{ route('login') }}">
                @csrf
                
                <!-- Email Address -->
                <div class="form-control w-full">
                    <label class="label" for="email">
                        <span class="label-text">{{ __('Email Address') }}</span>
                    </label>
                    <input id="email"
                           type="email"
                           class="input input-bordered w-full @error('email') input-error @enderror"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           autocomplete="email"
                           autofocus>
                    @error('email')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                    @enderror
                </div>
                
                <!-- Password -->
                <div class="form-control w-full mt-4">
                    <label class="label" for="password">
                        <span class="label-text">{{ __('Password') }}</span>
                    </label>
                    <input id="password"
                           type="password"
                           class="input input-bordered w-full @error('password') input-error @enderror"
                           name="password"
                           required
                           autocomplete="current-password">
                    @error('password')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                    @enderror
                </div>
                
                <!-- Remember Me -->
                <div class="form-control mt-6">
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" name="remember" id="remember" class="checkbox" {{ old('remember') ? 'checked' : '' }}>
                        <span class="label-text">{{ __('Remember Me') }}</span>
                    </label>
                </div>
                
                <div class="flex items-center justify-end mt-6">
                    @if (Route::has('password.request'))
                        <a class="link link-hover text-sm" href="{{ route('password.request') }}">
                            {{ __('Forgot Your Password?') }}
                        </a>
                    @endif
                    
                    <button type="submit" class="btn btn-primary ml-4">
                        {{ __('Login') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
