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
											{{-- MODIFIED: Corrected route to books page --}}
											<li>Added at least one book to your profile. <a href="{{ route('profile.books.edit') }}" class="font-semibold underline">Go to Books</a></li>
										@endif
									</ul>
								</div>
							</div>
						</div>
					@endif
					
					{{-- New Website Form --}}
					@if($prerequisitesMet)
						<div class="card bg-base-200 mb-6">
							<div class="card-body">
								<h2 class="card-title">Create a New Website</h2>
								<p>Configure your new project.</p>
								<form method="POST" action="{{ route('websites.store') }}" class="space-y-4">
									@csrf
									{{-- Website Name --}}
									<div>
										<label class="label" for="name">
											<span class="label-text">Website Name *</span>
										</label>
										<input type="text" id="name" name="name" placeholder="My Awesome Author Site" class="input input-bordered w-full" required value="{{ old('name') }}" />
										@error('name')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
									</div>
									
									{{-- NEW: Website URL/Slug Input --}}
									<div>
										<label class="label" for="slug">
											<span class="label-text">Website URL *</span>
										</label>
										<div class="join w-full">
											{{-- MODIFIED: Replaced route() with url() to prevent URL generation error --}}
											<span class="join-item btn btn-disabled !bg-base-300 !border-base-300 text-base-content/50">{{ url('/website') }}/</span>
											<input type="text" id="slug" name="slug" placeholder="my-awesome-site" class="input input-bordered join-item w-full" required value="{{ old('slug', $suggestedSlug) }}" />
										</div>
										<div id="slug-feedback" class="text-sm mt-1 h-5"></div>
										@error('slug')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
									</div>
									
									{{-- Primary Book --}}
									<div>
										<label class="label" for="primary_book_id">
											<span class="label-text">Primary Book *</span>
										</label>
										<select name="primary_book_id" class="select select-bordered w-full" required>
											<option disabled selected>Select the main book to feature</option>
											@foreach($userBooks as $book)
												<option value="{{ $book->id }}" @selected(old('primary_book_id') == $book->id)>
													{{ $book->title }} {{ $book->series_name ? "({$book->series_name} #{$book->series_number})" : '' }}
												</option>
											@endforeach
										</select>
										@error('primary_book_id')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
									</div>
									
									{{-- Featured Books --}}
									@if(count($userBooks) > 1)
										<div class="space-y-2">
											<label class="label"><span class="label-text">Additional Books (Optional)</span></label>
											<p class="text-sm opacity-70">Select other books to showcase.</p>
											<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 pt-2">
												@foreach($userBooks as $book)
													<div class="form-control">
														<label class="label cursor-pointer justify-start gap-4">
															<input type="checkbox" name="featured_book_ids[]" value="{{ $book->id }}" class="checkbox" />
															<span class="label-text">{{ $book->title }}</span>
														</label>
													</div>
												@endforeach
											</div>
											@error('featured_book_ids.*')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
										</div>
									@endif
									
									<div class="card-actions justify-end">
										<button type="submit" class="btn btn-primary">Create Website</button>
									</div>
								</form>
							</div>
						</div>
					@endif
					
					{{-- Website List --}}
					@if($websites->isEmpty() && $prerequisitesMet)
						<p class="text-center opacity-70 mt-4">
							You haven't created any websites yet. Use the form above to start!
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
												{{-- MODIFIED: Route now uses the website object, which resolves to the slug --}}
												<a href="{{ route('websites.show', $website) }}">{{ $website->name }}</a>
											</h2>
											<p>Created: {{ $website->created_at->toFormattedDateString() }}</p>
											<div class="card-actions justify-end">
												{{-- NEW: Settings button to open the slug editing modal --}}
												<button class="btn btn-ghost btn-sm" onclick="document.getElementById('slug_modal_{{ $website->id }}').showModal()">Settings</button>
												<a href="{{ route('websites.show', $website) }}" class="btn btn-outline btn-sm">Open Editor</a>
											</div>
										</div>
									</div>
									
									{{-- NEW: Modal for editing the website slug --}}
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
														{{-- MODIFIED: Replaced route() with url() to prevent URL generation error --}}
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
				
				</div>
			</div>
		</div>
	</div>
@endsection

{{-- NEW: Add JavaScript for real-time slug validation --}}
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
			
			// For the "Create Website" form
			const createForm = document.querySelector('form[action="{{ route("websites.store") }}"]');
			if (createForm) {
				const createFormSlugInput = createForm.querySelector('#slug');
				const createFormSlugFeedback = createForm.querySelector('#slug-feedback');
				const createFormSubmitBtn = createForm.querySelector('button[type="submit"]');
				if (createFormSlugInput && createFormSlugFeedback && createFormSubmitBtn) {
					createFormSlugInput.addEventListener('input', () => {
						handleSlugInput(createFormSlugInput, createFormSlugFeedback, createFormSubmitBtn);
					});
				}
			}
			
			
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
