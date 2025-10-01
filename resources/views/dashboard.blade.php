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
					
					{{-- MODIFIED: Replaced inline form with a button that opens a modal --}}
					@if($prerequisitesMet)
						<div class="mb-6">
							<button class="btn btn-primary" onclick="create_website_modal.showModal()">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
								Create New Website
							</button>
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
	
	{{-- NEW: Modal for creating a new website --}}
	<dialog id="create_website_modal" class="modal">
		<div class="modal-box w-11/12 max-w-3xl">
			<form method="dialog">
				<button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
			</form>
			<h3 class="font-bold text-lg">Create a New Website</h3>
			<p class="py-2 text-sm">Configure your new project.</p>
			<div class="divider"></div>
			
			<form method="POST" action="{{ route('websites.store') }}" class="space-y-4 pt-2">
				@csrf
				{{-- Website Name --}}
				<div>
					<label class="label" for="name">
						<span class="label-text">Website Name *</span>
					</label>
					<input type="text" id="name" name="name" placeholder="My Awesome Author Site" class="input input-bordered w-full" required value="{{ old('name') }}" />
					@error('name')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
				</div>
				
				{{-- Website URL/Slug Input --}}
				<div>
					<label class="label" for="slug">
						<span class="label-text">Website URL *</span>
					</label>
					<div class="join w-full">
						<span class="join-item btn btn-disabled !bg-base-300 !border-base-300 text-base-content/50">{{ url('/website') }}/</span>
						<input type="text" id="slug" name="slug" placeholder="my-awesome-site" class="input input-bordered join-item w-full" required value="{{ old('slug', $suggestedSlug) }}" />
					</div>
					<div id="slug-feedback" class="text-sm mt-1 h-5"></div>
					@error('slug')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
				</div>
				
				{{-- MODIFIED: Website Style Dropdown with custom option --}}
				<div>
					<label class="label" for="website_style">
						<span class="label-text">Website Style *</span>
					</label>
					<select name="website_style" id="website_style" class="select select-bordered w-full" required>
						<option disabled selected value="">Select a visual style</option>
						<option value="Modern & Minimal" @selected(old('website_style') == 'Modern & Minimal')>Modern & Minimal</option>
						<option value="Classic & Elegant" @selected(old('website_style') == 'Classic & Elegant')>Classic & Elegant</option>
						<option value="Dark & Mysterious" @selected(old('website_style') == 'Dark & Mysterious')>Dark & Mysterious (for Thrillers/Fantasy)</option>
						<option value="Whimsical & Fun" @selected(old('website_style') == 'Whimsical & Fun')>Whimsical & Fun (for Children's/Comedy)</option>
						<option value="Bold & Action-Packed" @selected(old('website_style') == 'Bold & Action-Packed')>Bold & Action-Packed (for Sci-Fi/Action)</option>
						<option value="Professional & Informative" @selected(old('website_style') == 'Professional & Informative')>Professional & Informative (for Non-Fiction)</option>
						<option value="Custom" @selected(old('website_style') == 'Custom')>I want to describe the style myself...</option>
					</select>
					@error('website_style')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
				</div>
				
				{{-- NEW: Custom Website Style Input (initially hidden) --}}
				<div id="custom-style-container" style="display: none;">
					<label class="label" for="custom_website_style">
						<span class="label-text">Describe Your Desired Style *</span>
					</label>
					<input type="text" id="custom_website_style" name="custom_website_style" placeholder="e.g., 'A vintage sci-fi look with typewriter fonts'" class="input input-bordered w-full" value="{{ old('custom_website_style') }}" />
					@error('custom_website_style')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
				</div>
				
				{{-- Primary Book --}}
				<div>
					<label class="label" for="primary_book_id">
						<span class="label-text">Primary Book *</span>
					</label>
					<select name="primary_book_id" id="primary_book_select" class="select select-bordered w-full" required>
						<option disabled selected value="">Select the main book to feature</option>
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
					<div class="space-y-2 pt-2">
						<label class="label"><span class="label-text">Additional Books (Optional)</span></label>
						<p class="text-sm opacity-70 -mt-2">Select other books to showcase on the website.</p>
						<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
							@foreach($userBooks as $book)
								<div class="form-control">
									<label class="label cursor-pointer justify-start gap-4">
										<input type="checkbox" name="featured_book_ids[]" value="{{ $book->id }}" class="checkbox featured-book-checkbox" @checked(is_array(old('featured_book_ids')) && in_array($book->id, old('featured_book_ids')))>
										<span class="label-text">{{ $book->title }}</span>
									</label>
								</div>
							@endforeach
						</div>
						@error('featured_book_ids.*')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
					</div>
				@endif
				
				<div class="modal-action pt-4">
					<form method="dialog"><button class="btn">Cancel</button></form>
					<button type="submit" class="btn btn-primary">Create Website</button>
				</div>
			</form>
		</div>
		<form method="dialog" class="modal-backdrop"><button>close</button></form>
	</dialog>
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
			
			// MODIFIED: Target the form inside the new modal
			const createForm = document.querySelector('#create_website_modal form[action="{{ route("websites.store") }}"]');
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
			
			// MODIFIED: Target the primary book select inside the new modal
			const primaryBookSelect = document.querySelector('#create_website_modal #primary_book_select');
			const featuredBookCheckboxes = document.querySelectorAll('#create_website_modal .featured-book-checkbox');
			
			if (primaryBookSelect && featuredBookCheckboxes.length > 0) {
				const syncFeaturedBooks = () => {
					const selectedPrimaryId = primaryBookSelect.value;
					
					featuredBookCheckboxes.forEach(checkbox => {
						const parentLabel = checkbox.closest('label');
						if (checkbox.value === selectedPrimaryId) {
							checkbox.checked = false;
							checkbox.disabled = true;
							if (parentLabel) {
								parentLabel.classList.add('opacity-50', 'cursor-not-allowed');
							}
						} else {
							checkbox.disabled = false;
							if (parentLabel) {
								parentLabel.classList.remove('opacity-50', 'cursor-not-allowed');
							}
						}
					});
				};
				
				// Run on initial load to handle validation errors and old input
				syncFeaturedBooks();
				
				// Run whenever the primary book selection changes
				primaryBookSelect.addEventListener('change', syncFeaturedBooks);
			}
			
			// --- NEW: Logic for custom website style input ---
			const websiteStyleSelect = document.querySelector('#create_website_modal #website_style');
			const customStyleContainer = document.querySelector('#create_website_modal #custom-style-container');
			
			if (websiteStyleSelect && customStyleContainer) {
				const toggleCustomStyleInput = () => {
					if (websiteStyleSelect.value === 'Custom') {
						customStyleContainer.style.display = 'block';
					} else {
						customStyleContainer.style.display = 'none';
					}
				};
				
				// Run on initial load to handle old input from validation errors
				toggleCustomStyleInput();
				
				// Run whenever the style selection changes
				websiteStyleSelect.addEventListener('change', toggleCustomStyleInput);
			}
		});
	</script>
@endpush
