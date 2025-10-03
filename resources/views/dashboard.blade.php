@extends('layouts.app')

@section('content')
	<div class="py-12">
		<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
			<div class="bg-base-100 overflow-hidden shadow-sm sm:rounded-lg">
				<div class="p-6 text-base-content">
					@if (session('success'))
						<div role="alert" class="alert alert-success mb-6">
							<svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
							<span>{{ session('success') }}</span>
						</div>
					@endif
					
					@if (session('error'))
						<div role="alert" class="alert alert-error mb-6">
							<svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
							<span>{{ session('error') }}</span>
						</div>
					@endif
					
					{{-- NEW: Instructions Section --}}
					<div class="mb-6 p-4 bg-base-200 rounded-lg">
						<h2 class="font-bold text-lg mb-2">How to Use the AI Website Builder</h2>
						<p class="text-sm opacity-80">
							Once you create a website, you will be taken to the editor. You can ask the AI assistant to make changes to your site. For example, try asking it to:
						</p>
						<ul class="list-disc pl-5 mt-2 text-sm space-y-1">
							<li>Change the background to a simple color consistent with the book cover.</li>
							<li>Change the title font to something closer to the font on the book cover.</li>
							<li>Try another font, more similar to the text on the cover.</li>
							<li>Change the banner background to a more faded image of the cover image.</li>
							<li>Connect the Facebook link to my Facebook account.</li>
							<li>Add another section for the content of the book.</li>
						</ul>
					</div>
					
					{{-- Prerequisite Check --}}
					@if(!$prerequisitesMet)
						<div role="alert" class="alert alert-error mb-6">
							<svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
							<div>
								<h3 class="font-bold">Action Required</h3>
								<div class="text-xs">
									Before creating a website, please ensure you have:
									<ul class="list-disc pl-5 mt-2">
										@if(!$profileComplete)
											<li>Completed your profile (name, bio, and profile photo). <a href="{{ route('profile.edit') }}" class="font-semibold underline">Go to Profile</a></li>
										@endif
										@if(!$hasBooks)
											<li>Added at least one book to your profile. <a href="{{ route('profile.books.edit') }}" class="font-semibold underline">Go to Books</a></li>
										@endif
									</ul>
								</div>
							</div>
						</div>
					@endif
					
					{{-- MODIFIED: Changed button to a link pointing to the new create page. --}}
					@if($prerequisitesMet)
						<div class="mb-6">
							<a href="{{ route('websites.create') }}" class="btn btn-primary">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
								Create New Website
							</a>
						</div>
					@endif
					
					{{-- Website List --}}
					@if($websites->isEmpty() && $prerequisitesMet)
						<p class="text-center opacity-70 mt-4">
							You haven't created any websites yet. Use the button above to start!
						</p>
					@endif
					
					@if($websites->isNotEmpty())
						<div>
							<h3 class="text-lg font-medium mb-4">Your Existing Websites</h3>
							<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
								@foreach($websites as $website)
									<div class="card bg-base-200 shadow-xl">
										<div class="card-body">
											<h2 class="card-title hover:text-primary">
												<a href="{{ route('websites.show', $website) }}">{{ $website->name }}</a>
											</h2>
											<p>Created: {{ $website->created_at->toFormattedDateString() }}</p>
											<div class="card-actions justify-end">
												<button class="btn btn-ghost btn-sm" onclick="document.getElementById('slug_modal_{{ $website->id }}').showModal()">Settings</button>
												<a href="{{ route('websites.show', $website) }}" class="btn btn-outline btn-sm">Open Editor</a>
											</div>
										</div>
									</div>
									
									<dialog id="slug_modal_{{ $website->id }}" class="modal">
										<div class="modal-box">
											<h3 class="font-bold text-lg">Website Settings for "{{ $website->name }}"</h3>
											<p class="py-2 text-sm">Change the public URL for your website.</p>
											<form method="POST" action="{{ route('websites.slug.update', $website) }}" class="space-y-4 pt-4 slug-update-form" data-website-id="{{ $website->id }}">
												@csrf
												@method('PATCH')
												<div>
													<label class="label" for="slug_{{ $website->id }}">
														<span class="label-text">Website URL *</span>
													</label>
													<div class="join w-full">
														<span class="join-item btn btn-disabled !bg-base-300 !border-base-300 text-base-content/50">{{ url('/website') }}/</span>
														<input type="text" id="slug_{{ $website->id }}" name="slug" class="input input-bordered join-item w-full slug-input" required value="{{ old('slug', $website->slug) }}" />
													</div>
													<div class="slug-feedback text-sm mt-1 h-5"></div>
													@error('slug')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
												</div>
												<div class="modal-action">
													<form method="dialog"><button class="btn">Cancel</button></form>
													<button type="submit" class="btn btn-primary">Save Changes</button>
												</div>
											</form>
										</div>
										<form method="dialog" class="modal-backdrop"><button>close</button></form>
									</dialog>
								@endforeach
							</div>
						</div>
					@endif
					
					{{-- NEW: Beta Notice Info Box --}}
					<div class="mt-8">
						<div role="alert" class="alert alert-info">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
							<span>This service is still in beta and currently open for free testing, expect results to be varied.</span>
						</div>
					</div>
				
				</div>
			</div>
		</div>
	</div>
	
	{{-- MODIFIED: The modal for creating a website has been removed from this file. --}}
@endsection

@push('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
			let debounceTimer;
			
			const checkSlugAvailability = async (slug, feedbackEl, submitBtn, ignoreId = null) => {
				// Basic regex for URL-safe characters
				if (!/^[a-zA-Z0-9-_]+$/.test(slug)) {
					feedbackEl.innerHTML = `<span class="text-error">Invalid format. Only letters, numbers, dashes, and underscores are allowed.</span>`;
					submitBtn.disabled = true;
					return;
				}
				
				if (slug.length < 3) {
					feedbackEl.innerHTML = `<span class="text-warning">URL must be at least 3 characters long.</span>`;
					submitBtn.disabled = true;
					return;
				}
				
				feedbackEl.innerHTML = `<span class="loading loading-dots loading-xs"></span> Checking availability...`;
				submitBtn.disabled = true;
				
				try {
					const response = await fetch('{{ route("websites.slug.check") }}', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							'Accept': 'application/json',
						},
						body: JSON.stringify({ slug: slug, ignore_id: ignoreId }),
					});
					
					const data = await response.json();
					
					if (data.available) {
						feedbackEl.innerHTML = `<span class="text-success">Available!</span>`;
						submitBtn.disabled = false;
					} else {
						feedbackEl.innerHTML = `<span class="text-error">This URL is already taken.</span>`;
						submitBtn.disabled = true;
					}
				} catch (error) {
					console.error('Slug check error:', error);
					feedbackEl.innerHTML = `<span class="text-error">Could not verify URL.</span>`;
					submitBtn.disabled = true;
				}
			};
			
			const handleSlugInput = (inputEl, feedbackEl, submitBtn, ignoreId = null) => {
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(() => {
					const slug = inputEl.value.trim();
					if (slug) {
						checkSlugAvailability(slug, feedbackEl, submitBtn, ignoreId);
					} else {
						feedbackEl.innerHTML = '';
						submitBtn.disabled = true; // Disable if empty
					}
				}, 500); // 500ms debounce
			};
			
			// MODIFIED: Removed the script logic for the create website modal.
			
			// For all "Update Slug" modals
			document.querySelectorAll('.slug-update-form').forEach(form => {
				const slugInput = form.querySelector('.slug-input');
				const feedbackEl = form.querySelector('.slug-feedback');
				const submitBtn = form.querySelector('button[type="submit"]');
				const websiteId = form.dataset.websiteId;
				
				if (slugInput && feedbackEl && submitBtn) {
					slugInput.addEventListener('input', () => {
						handleSlugInput(slugInput, feedbackEl, submitBtn, websiteId);
					});
				}
			});
		});
	</script>
@endpush
