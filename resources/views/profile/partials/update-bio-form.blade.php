<section>
	<header>
		<h2 class="text-lg font-medium text-base-content">
			{{ __('Author Bio') }}
		</h2>
		<p class="mt-1 text-sm text-base-content/70">
			{{ __('Tell readers a bit about yourself. This will be displayed on your public author page.') }}
		</p>
	</header>
	
	<form method="post" action="{{ route('profile.bio.update') }}" class="mt-6 space-y-6">
		@csrf
		@method('patch')
		
		<div>
			<label for="bio" class="label">
				<span class="label-text">{{ __('Your Bio') }}</span>
			</label>
			<textarea id="bio" name="bio" class="textarea textarea-bordered w-full min-h-[150px]" maxlength="5000">{{ old('bio', $user->bio) }}</textarea>
			@error('bio')
			<p class="text-error text-sm mt-1">{{ $message }}</p>
			@enderror
		</div>
		
		<div class="flex items-center justify-between gap-4">
			<div class="flex items-center gap-4">
				<button type="submit" class="btn btn-primary">{{ __('Save Bio') }}</button>
				@if (session('status') === 'profile-bio-updated')
					<p class="text-sm text-base-content/70">{{ __('Saved.') }}</p>
				@endif
			</div>
			{{-- The AI Placeholder button would require custom JavaScript to function --}}
		</div>
	</form>
</section>
