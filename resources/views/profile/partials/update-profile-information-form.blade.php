<section>
	<header>
		<h2 class="text-lg font-medium text-base-content">
			{{ __('Profile Information') }}
		</h2>
		
		<p class="mt-1 text-sm text-base-content/70">
			{{ __("Update your account's profile information and email address.") }}
		</p>
	</header>
	
	{{-- This form is used for re-sending the verification email --}}
{{--	<form id="send-verification" method="post" action="{{ route('verification.send') }}">--}}
{{--		@csrf--}}
{{--	</form>--}}
	
	<form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
		@csrf
		@method('patch')
		
		{{-- Name Field --}}
		<div>
			<label for="name" class="label">
				<span class="label-text">{{ __('Name') }}</span>
			</label>
			<input id="name" name="name" type="text" class="input input-bordered w-full max-w-xs" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name" />
			@error('name')
			<p class="text-error text-sm mt-1">{{ $message }}</p>
			@enderror
		</div>
		
		{{-- Email Field --}}
		<div>
			<label for="email" class="label">
				<span class="label-text">{{ __('Email') }}</span>
			</label>
			<input id="email" name="email" type="email" class="input input-bordered w-full max-w-xs" value="{{ old('email', $user->email) }}" required autocomplete="username" />
			@error('email')
			<p class="text-error text-sm mt-1">{{ $message }}</p>
			@enderror
			
			{{-- Email Verification Logic --}}
			@if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
				<div>
					<p class="text-sm mt-2 text-base-content/80">
						{{ __('Your email address is unverified.') }}
						
						<button form="send-verification" class="underline text-sm hover:text-base-content rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
							{{ __('Click here to re-send the verification email.') }}
						</button>
					</p>
					
					@if (session('status') === 'verification-link-sent')
						<p class="mt-2 font-medium text-sm text-success">
							{{ __('A new verification link has been sent to your email address.') }}
						</p>
					@endif
				</div>
			@endif
		</div>
		
		<div class="flex items-center gap-4">
			<button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
			
			@if (session('status') === 'profile-updated')
				<p class="text-sm text-base-content/70">{{ __('Saved.') }}</p>
			@endif
		</div>
	</form>
</section>
