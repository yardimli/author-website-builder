<section>
	<header>
		<h2 class="text-lg font-medium text-base-content">
			{{ __('Profile Photo') }}
		</h2>
		<p class="mt-1 text-sm text-base-content/70">
			{{ __('Update your profile photo. Recommended size: 200x200px. Max 2MB.') }}
		</p>
	</header>
	
	{{-- Display current profile photo --}}
	<div class="mt-6 flex items-center gap-4">
		<div class="avatar">
			<div class="w-24 rounded-xl ring ring-primary ring-offset-base-100 ring-offset-2">
				{{-- Use the accessor from the User model to get the photo URL --}}
				<img src="{{ $user->profile_photo_url ?? 'https://ui-avatars.com/api/?name='.urlencode($user->name) }}" alt="{{ $user->name }}" />
			</div>
		</div>
		
		{{-- Form for uploading a new photo --}}
		<form method="post" action="{{ route('profile.photo.update') }}" class="flex-grow space-y-2" enctype="multipart/form-data">
			@csrf
			{{-- NEW: Add hidden input if in wizard mode --}}
			@if(isset($isWizard) && $isWizard)
				<input type="hidden" name="is_wizard" value="1">
			@endif
			<input type="file" name="photo" class="file-input file-input-bordered w-full max-w-xs" />
			@error('photo')
			<p class="text-error text-sm mt-1">{{ $message }}</p>
			@enderror
			<button type="submit" class="btn btn-sm btn-primary">{{ __('Save Photo') }}</button>
		</form>
		
		{{-- Form for removing an existing photo --}}
		@if ($user->profile_photo_path)
			<form method="post" action="{{ route('profile.photo.delete') }}" onsubmit="return confirm('Are you sure you want to remove your profile photo?');">
				@csrf
				@method('delete')
				{{-- NEW: Add hidden input if in wizard mode --}}
				@if(isset($isWizard) && $isWizard)
					<input type="hidden" name="is_wizard" value="1">
				@endif
				<button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('Remove') }}</button>
			</form>
		@endif
	</div>
	
	{{-- Display success messages --}}
	@if (session('status') === 'profile-photo-updated')
		<p class="text-sm text-success mt-2">{{ __('Profile photo saved.') }}</p>
	@endif
	@if (session('status') === 'profile-photo-deleted')
		<p class="text-sm text-success mt-2">{{ __('Profile photo removed.') }}</p>
	@endif
</section>
