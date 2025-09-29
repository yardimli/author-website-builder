<section class="space-y-6">
	<header>
		<h2 class="text-lg font-medium text-base-content">
			{{ __('Delete Account') }}
		</h2>
		
		<p class="mt-1 text-sm text-base-content/70">
			{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
		</p>
	</header>
	
	{{-- This button opens the modal --}}
	<button class="btn btn-error" onclick="confirm_user_deletion.showModal()">{{ __('Delete Account') }}</button>
	
	{{-- DaisyUI Modal for confirmation --}}
	<dialog id="confirm_user_deletion" class="modal">
		<div class="modal-box">
			<form method="post" action="{{ route('profile.destroy') }}">
				@csrf
				@method('delete')
				
				<h2 class="text-lg font-bold text-base-content">
					{{ __('Are you sure you want to delete your account?') }}
				</h2>
				
				<p class="mt-1 text-sm text-base-content/70">
					{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
				</p>
				
				<div class="mt-6">
					<label for="password" class="label sr-only">{{ __('Password') }}</label>
					<input
						id="password"
						name="password"
						type="password"
						class="input input-bordered w-full"
						placeholder="{{ __('Password') }}"
					/>
					@error('password', 'userDeletion')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
				</div>
				
				<div class="mt-6 flex justify-end">
					{{-- The form method="dialog" is a native HTML way to close a dialog --}}
					<form method="dialog">
						<button class="btn">Cancel</button>
					</form>
					<button class="btn btn-error ml-3">
						{{ __('Delete Account') }}
					</button>
				</div>
			</form>
		</div>
		{{-- Clicking the backdrop also closes the modal --}}
		<form method="dialog" class="modal-backdrop">
			<button>close</button>
		</form>
	</dialog>
</section>
