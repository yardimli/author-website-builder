<section>
	<header>
		<h2 class="text-lg font-medium text-base-content">
			{{ __('Author Bio') }}
		</h2>
		<p class="mt-1 text-sm text-base-content/70">
			{{ __('Tell readers a bit about yourself. This will be displayed on your public author page.') }}
		</p>
	</header>
	
	{{-- MODIFIED: Added id to form for JS targeting --}}
	<form id="bio-form" method="post" action="{{ route('profile.bio.update') }}" class="mt-6 space-y-6">
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
		
		{{-- MODIFIED: Changed layout to justify-between and added AI button --}}
		<div class="flex items-center justify-between gap-4">
			<div class="flex items-center gap-4">
				<button type="submit" class="btn btn-primary">{{ __('Save Bio') }}</button>
				@if (session('status') === 'profile-bio-updated')
					<p class="text-sm text-base-content/70">{{ __('Saved.') }}</p>
				@endif
			</div>
			
			{{-- NEW: AI generation button --}}
			<button type="button" id="generate-bio-btn" class="btn btn-ghost">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></svg>
				Generate or Expand with AI
			</button>
		</div>
	</form>
	
	{{-- NEW: Added DaisyUI modal for displaying AI errors --}}
	<dialog id="ai_error_modal" class="modal">
		<div class="modal-box">
			<h3 class="font-bold text-lg text-error">
				<svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6 inline-block mr-2" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
				AI Generation Failed
			</h3>
			<p id="ai_error_message" class="py-4">An unknown error occurred. Please check the console for details or try again later.</p>
			<div class="modal-action">
				<form method="dialog">
					<button class="btn">Close</button>
				</form>
			</div>
		</div>
		<form method="dialog" class="modal-backdrop"><button>close</button></form>
	</dialog>
</section>

{{-- MODIFIED: Script section updated to use the new modal --}}
@push('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const generateBtn = document.getElementById('generate-bio-btn');
			if (generateBtn) {
				generateBtn.addEventListener('click', async function() {
					const form = document.getElementById('bio-form');
					const bioTextarea = form.querySelector('#bio');
					const currentBio = bioTextarea.value;
					const originalButtonContent = generateBtn.innerHTML;
					const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
					
					generateBtn.disabled = true;
					generateBtn.innerHTML = `<span class="loading loading-spinner loading-xs"></span> Generating...`;
					
					try {
						const response = await fetch("{{ route('profile.bio.generate') }}", {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-CSRF-TOKEN': csrfToken,
								'Accept': 'application/json',
							},
							body: JSON.stringify({ current_bio: currentBio }),
						});
						
						if (!response.ok) {
							const errorData = await response.json();
							throw new Error(errorData.error || 'Failed to generate bio. The server returned an error.');
						}
						
						const data = await response.json();
						bioTextarea.value = data.generated_bio;
						
					} catch (error) {
						console.error('AI Bio generation error:', error);
						
						// --- MODIFIED: Use DaisyUI modal instead of alert ---
						const errorModal = document.getElementById('ai_error_modal');
						const errorMessageElement = document.getElementById('ai_error_message');
						if (errorModal && errorMessageElement) {
							errorMessageElement.textContent = error.message;
							errorModal.showModal();
						} else {
							// Fallback to alert if modal elements are somehow not found
							alert('AI Error: ' + error.message);
						}
						
					} finally {
						generateBtn.disabled = false;
						generateBtn.innerHTML = originalButtonContent;
					}
				});
			}
		});
	</script>
@endpush
