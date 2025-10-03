{{-- NEW: This entire file is new. --}}
@extends('layouts.app')

@section('content')
	<div class="py-12">
		<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
			<div class="bg-base-100 overflow-hidden shadow-sm sm:rounded-lg">
				<div class="p-6 sm:p-8 text-base-content">
					{{-- NEW: Conditionally display the wizard header --}}
					@if($isWizard)
						@include('partials.wizard-header', ['step' => $wizardStep])
					@endif
					
					<header class="flex justify-between items-center">
						<div>
							<h2 class="text-lg font-medium text-base-content">Create a New Website</h2>
							<p class="mt-1 text-sm text-base-content/70">
								Configure your new project. The AI will use this information for the initial build.
							</p>
						</div>
						{{-- MODIFIED: Hide back button during wizard --}}
						@if(!$isWizard)
							<a href="{{ route('dashboard') }}" class="btn btn-ghost">&larr; Back to Dashboard</a>
						@endif
					</header>
					
					<div class="divider mt-4 mb-6"></div>
					
					<form id="create-website-form" method="POST" action="{{ route('websites.store') }}" class="space-y-4">
						@csrf
						{{-- Website Name --}}
						<div>
							<label class="label" for="name">
								<span class="label-text">Website Name *</span>
							</label>
							<input type="text" id="name" name="name" placeholder="My Awesome Author Site" class="input input-bordered w-full max-w-lg" required value="{{ old('name') }}" />
							@error('name')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
						</div>
						
						{{-- Website URL/Slug Input --}}
						<div>
							<label class="label" for="slug">
								<span class="label-text">Website URL *</span>
							</label>
							<div class="join w-full max-w-lg">
								<span class="join-item btn btn-disabled !bg-base-300 !border-base-300 text-base-content/50">{{ url('/website') }}/</span>
								<input type="text" id="slug" name="slug" placeholder="my-awesome-site" class="input input-bordered join-item w-full" required value="{{ old('slug', $suggestedSlug) }}" />
							</div>
							<div id="slug-feedback" class="text-sm mt-1 h-5"></div>
							@error('slug')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
						</div>
						
						{{-- Website Style Dropdown with custom option --}}
						<div>
							<label class="label" for="website_style">
								<span class="label-text">Website Style *</span>
							</label>
							<select name="website_style" id="website_style" class="select select-bordered w-full max-w-lg" required>
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
						
						{{-- Custom Website Style Input (initially hidden) --}}
						<div id="custom-style-container" style="display: none;">
							<label class="label" for="custom_website_style">
								<span class="label-text">Describe Your Desired Style *</span>
							</label>
							<input type="text" id="custom_website_style" name="custom_website_style" placeholder="e.g., 'A vintage sci-fi look with typewriter fonts'" class="input input-bordered w-full max-w-lg" value="{{ old('custom_website_style') }}" />
							@error('custom_website_style')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
						</div>
						
						{{-- Primary Book --}}
						<div>
							<label class="label" for="primary_book_id">
								<span class="label-text">Primary Book *</span>
							</label>
							<select name="primary_book_id" id="primary_book_select" class="select select-bordered w-full max-w-lg" required>
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
								<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 max-w-lg">
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
						
						<div class="pt-6 flex items-center gap-4">
							<button type="submit" class="btn btn-primary">
								{{ $isWizard ? 'Finish & Create Website' : 'Create Website' }}
							</button>
							{{-- MODIFIED: Show Cancel or Finish Later button --}}
							@if($isWizard)
								<a href="{{ route('dashboard') }}" class="btn btn-ghost">Finish Later</a>
							@else
								<a href="{{ route('dashboard') }}" class="btn btn-ghost">Cancel</a>
							@endif
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
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
			
			// Logic for the create form on this page
			const createForm = document.getElementById('create-website-form');
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
			
			// Logic for disabling featured book if it's selected as primary
			const primaryBookSelect = document.getElementById('primary_book_select');
			const featuredBookCheckboxes = document.querySelectorAll('.featured-book-checkbox');
			
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
			
			// Logic for showing/hiding the custom website style input
			const websiteStyleSelect = document.getElementById('website_style');
			const customStyleContainer = document.getElementById('custom-style-container');
			
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
