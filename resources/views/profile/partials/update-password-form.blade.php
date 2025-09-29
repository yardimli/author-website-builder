<section>
	<header>
		<h2 class="text-lg font-medium text-base-content">
			{{ __('Update Password') }}
		</h2>
		
		<p class="mt-1 text-sm text-base-content/70">
			{{ __('Ensure your account is using a long, random password to stay secure.') }}
		</p>
	</header>
	
	<form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
		@csrf
		@method('put')
		
		<div>
			<label for="current_password" class="label"><span class="label-text">{{ __('Current Password') }}</span></label>
			<input id="current_password" name="current_password" type="password" class="input input-bordered w-full max-w-xs" autocomplete="current-password" />
			@error('current_password', 'updatePassword')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
		</div>
		
		<div>
			<label for="password" class="label"><span class="label-text">{{ __('New Password') }}</span></label>
			<input id="password" name="password" type="password" class="input input-bordered w-full max-w-xs" autocomplete="new-password" />
			@error('password', 'updatePassword')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
		</div>
		
		<div>
			<label for="password_confirmation" class="label"><span class="label-text">{{ __('Confirm Password') }}</span></label>
			<input id="password_confirmation" name="password_confirmation" type="password" class="input input-bordered w-full max-w-xs" autocomplete="new-password" />
			@error('password_confirmation', 'updatePassword')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
		</div>
		
		<div class="flex items-center gap-4">
			<button class="btn btn-primary">{{ __('Save') }}</button>
			
			@if (session('status') === 'password-updated')
				<p class="text-sm text-base-content/70">{{ __('Saved.') }}</p>
			@endif
		</div>
	</form>
</section>
