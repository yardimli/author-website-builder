<section>
	{{-- MODIFIED: Added a flex container for buttons --}}
	<header class="flex flex-wrap justify-between items-center gap-4">
		<div>
			<h2 class="text-lg font-medium text-base-content">Your Books</h2>
			<p class="mt-1 text-sm text-base-content/70">
				Manage the books you want to showcase.
			</p>
		</div>
		{{-- MODIFIED: Grouped buttons together --}}
		<div class="flex items-center gap-2">
			{{-- NEW: Button to link to the new import page --}}
			<a href="{{ route('profile.import') }}" class="btn btn-outline">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
				Import from BookCoverZone
			</a>
			{{-- Button to open the modal for adding a new book --}}
			<button class="btn btn-primary" onclick="add_book_modal.showModal()">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
				Add New Book
			</button>
		</div>
	</header>
	
	{{-- List of existing books --}}
	<div class="mt-6 space-y-6">
		@forelse($books as $book)
			<div class="card card-side bg-base-200 shadow-md flex-col sm:flex-row">
				<figure class="p-4 flex-shrink-0 sm:w-32 md:w-40 flex justify-center">
					<img src="{{ $book->cover_image_url ?? 'https://via.placeholder.com/150x220.png/EFEFEF/AAAAAA?text=No+Cover' }}" alt="{{ $book->title }} cover" class="w-28 h-42 object-cover rounded" />
				</figure>
				<div class="card-body">
					<div class="flex justify-between items-start">
						<div>
							<h3 class="card-title">{{ $book->title }}</h3>
							<p class="text-sm opacity-80">{{ $book->subtitle }}</p>
							@if($book->series_name)
								<p class="text-xs opacity-70">({{ $book->series_name }}, Book {{ $book->series_number }})</p>
							@endif
							@if($book->published_at)
								<p class="text-xs opacity-70 mt-1">Published: {{ $book->published_at->format('M j, Y') }}</p>
							@endif
						</div>
						<div class="card-actions justify-end flex-shrink-0 ml-2">
							{{-- MODIFIED: Added Edit button to open the edit modal --}}
							<button class="btn btn-outline btn-sm" onclick="document.getElementById('edit_book_modal_{{ $book->id }}').showModal()">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
								<span class="hidden sm:inline ml-1">Edit</span>
							</button>
							{{-- MODIFIED: Delete button now opens a confirmation modal --}}
							<button class="btn btn-error btn-sm" onclick="document.getElementById('delete_book_modal_{{ $book->id }}').showModal()">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
								<span class="hidden sm:inline ml-1">Delete</span>
							</button>
						</div>
					</div>
				</div>
			</div>
			
			{{-- MODIFIED: Added Edit Modal for each book --}}
			<dialog id="edit_book_modal_{{ $book->id }}" class="modal">
				<div class="modal-box w-11/12 max-w-5xl">
					<form method="dialog">
						<button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
					</form>
					<h3 class="font-bold text-lg">Editing: {{ $book->title }}</h3>
					{{-- The form partial is reused for consistency --}}
					@include('profile.partials.book-form-fields', ['book' => $book, 'isNew' => false])
				</div>
			</dialog>
			
			{{-- MODIFIED: Added Delete Confirmation Modal for each book --}}
			<dialog id="delete_book_modal_{{ $book->id }}" class="modal">
				<div class="modal-box">
					<h3 class="font-bold text-lg">Are you sure?</h3>
					<p class="py-4">This action cannot be undone. This will permanently delete the book "{{ $book->title }}" and its cover image.</p>
					<div class="modal-action">
						<form method="dialog">
							<button class="btn">Cancel</button>
						</form>
						<form method="POST" action="{{ route('profile.books.destroy', $book) }}">
							@csrf
							@method('DELETE')
							<button type="submit" class="btn btn-error">Yes, delete book</button>
						</form>
					</div>
				</div>
				<form method="dialog" class="modal-backdrop"><button>close</button></form>
			</dialog>
		
		@empty
			<p class="text-center text-base-content/70 py-4">You haven't added any books yet.</p>
		@endforelse
	</div>
	
	{{-- MODIFIED: "Add Book" modal now uses the reusable form partial --}}
	<dialog id="add_book_modal" class="modal">
		<div class="modal-box w-11/12 max-w-5xl">
			<form method="dialog">
				<button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
			</form>
			<h3 class="font-bold text-lg">Add a New Book</h3>
			@include('profile.partials.book-form-fields', ['book' => null, 'isNew' => true])
		</div>
	</dialog>
</section>

{{-- NEW: Added script section for AI generation, image preview, and conditional fields --}}
@push('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
			
			// --- AI Placeholder Generation ---
			async function generateAiPlaceholder(fieldType, form) {
				const title = form.querySelector(`[name="title"]`).value;
				const subtitle = form.querySelector(`[name="subtitle"]`).value;
				const button = form.querySelector(`.generate-${fieldType}-btn`);
				const textarea = form.querySelector(`[name="${fieldType}"]`);
				const originalButtonContent = button.innerHTML;
				
				if (!title) {
					alert("Please enter a Title before generating AI content.");
					return;
				}
				
				button.disabled = true;
				button.innerHTML = `<span class="loading loading-spinner loading-xs"></span> Generating...`;
				
				try {
					const routeName = fieldType === 'hook' ? '{{ route("profile.books.generate.hook") }}' : '{{ route("profile.books.generate.about") }}';
					const response = await fetch(routeName, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							'Accept': 'application/json',
						},
						body: JSON.stringify({ title, subtitle }),
					});
					
					if (!response.ok) {
						const errorData = await response.json();
						throw new Error(errorData.error || 'Failed to generate content.');
					}
					
					const data = await response.json();
					textarea.value = data.generated_text;
					
				} catch (error) {
					console.error(`AI ${fieldType} generation error:`, error);
					alert(`AI Error: ${error.message}`);
				} finally {
					button.disabled = false;
					button.innerHTML = originalButtonContent;
				}
			}
			
			// Attach event listeners to all AI buttons
			document.querySelectorAll('.generate-hook-btn').forEach(btn => {
				btn.addEventListener('click', () => generateAiPlaceholder('hook', btn.closest('form')));
			});
			document.querySelectorAll('.generate-about-btn').forEach(btn => {
				btn.addEventListener('click', () => generateAiPlaceholder('about', btn.closest('form')));
			});
			
			// --- Conditional Series Fields ---
			function toggleSeriesFields(form) {
				const isSeriesCheckbox = form.querySelector('.is-series-checkbox');
				const seriesFieldsContainer = form.querySelector('.series-fields-container');
				if (isSeriesCheckbox && seriesFieldsContainer) {
					seriesFieldsContainer.style.display = isSeriesCheckbox.checked ? 'grid' : 'none';
				}
			}
			
			document.querySelectorAll('.is-series-checkbox').forEach(checkbox => {
				toggleSeriesFields(checkbox.closest('form')); // Initial check
				checkbox.addEventListener('change', () => toggleSeriesFields(checkbox.closest('form')));
			});
			
			// --- Cover Image Preview ---
			function setupImagePreview(form) {
				const coverInput = form.querySelector('input[name="cover_image"]');
				const coverPreview = form.querySelector('.cover-image-preview');
				
				if (coverInput && coverPreview) {
					coverInput.addEventListener('change', function(e) {
						const file = e.target.files[0];
						if (file) {
							const reader = new FileReader();
							reader.onload = (event) => {
								coverPreview.src = event.target.result;
							}
							reader.readAsDataURL(file);
						}
					});
				}
			}
			
			document.querySelectorAll('form').forEach(form => {
				setupImagePreview(form);
			});
		});
	</script>
@endpush
