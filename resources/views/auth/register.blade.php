@extends('layouts.guest')

@section('content')
    {{-- MODIFIED: Replaced Bootstrap structure with DaisyUI/Tailwind CSS --}}
    <div class="card w-full bg-base-100">
        <div class="card-body">
            <h2 class="card-title justify-center text-2xl">{{ __('Register') }}</h2>
            
            <form method="POST" action="{{ route('register') }}">
                @csrf
                
                <!-- Name -->
                <div class="form-control w-full">
                    <label class="label" for="name"><span class="label-text">{{ __('Name') }}</span></label>
                    <input id="name" type="text" class="input input-bordered w-full @error('name') input-error @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>
                    @error('name')
                    <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>
                
                <!-- Email Address -->
                <div class="form-control w-full mt-4">
                    <label class="label" for="email"><span class="label-text">{{ __('Email Address') }}</span></label>
                    <input id="email" type="email" class="input input-bordered w-full @error('email') input-error @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">
                    @error('email')
                    <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>
                
                <!-- Password -->
                <div class="form-control w-full mt-4">
                    <label class="label" for="password"><span class="label-text">{{ __('Password') }}</span></label>
                    <input id="password" type="password" class="input input-bordered w-full @error('password') input-error @enderror" name="password" required autocomplete="new-password">
                    @error('password')
                    <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>
                
                <!-- Confirm Password -->
                <div class="form-control w-full mt-4">
                    <label class="label" for="password-confirm"><span class="label-text">{{ __('Confirm Password') }}</span></label>
                    <input id="password-confirm" type="password" class="input input-bordered w-full" name="password_confirmation" required autocomplete="new-password">
                </div>
                
                <div class="flex items-center justify-end mt-6">
                    <a class="link link-hover text-sm" href="{{ route('login') }}">
                        {{ __('Already registered?') }}
                    </a>
                    
                    <button type="submit" class="btn btn-primary ml-4">
                        {{ __('Register') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
