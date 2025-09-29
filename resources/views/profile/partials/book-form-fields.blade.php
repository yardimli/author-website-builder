{{--
This partial contains the form fields for adding or editing a book.
It expects the following variables:
- $book: The book object to edit, or null for a new book.
- $isNew: A boolean, true if this is for creating a new book.
--}}
<form method="POST" action="{{ $isNew ? route('profile.books.store') : route('profile.books.update', $book) }}" class="py-4 space-y-4" enctype="multipart/form-data">
	@csrf
	{{-- Use POST for update as defined in web.php to simplify form --}}
	
	{{-- Cover Image --}}
	<div class="flex items-start space-x-4">
		<img src="{{ $book->cover_image_url ?? 'https://via.placeholder.com/150x220.png/EFEFEF/AAAAAA?text=No+Cover' }}" alt="Cover preview" class="cover-image-preview w-24 h-36 object-cover rounded border" />
		<div class="flex-grow space-y-2">
			<label class="label"><span class="label-text">Cover Image</span></label>
			<input type="file" name="cover_image" class="file-input file-input-bordered w-full" accept="image/*" />
			@error('cover_image')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
			
			{{-- NEW: Add checkbox to remove existing cover, only shown when editing --}}
			@if(!$isNew && $book->cover_image_path)
				<div class="form-control">
					<label class="label cursor-pointer justify-start gap-2">
						<input type="checkbox" name="remove_cover_image" value="1" class="checkbox checkbox-sm" />
						<span class="label-text">Remove current cover image</span>
					</label>
				</div>
			@endif
		</div>
	</div>
	
	{{-- Title and Subtitle --}}
	<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
		<div>
			<label class="label"><span class="label-text">Title *</span></label>
			<input type="text" name="title" class="input input-bordered w-full" required value="{{ old('title', $book?->title) }}" />
			@error('title')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
		</div>
		<div>
			<label class="label"><span class="label-text">Subtitle</span></label>
			<input type="text" name="subtitle" class="input input-bordered w-full" value="{{ old('subtitle', $book?->subtitle) }}" />
			@error('subtitle')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
		</div>
	</div>
	
	{{-- Hook / Tagline --}}
	<div>
		<div class="flex justify-between items-center">
			<label class="label"><span class="label-text">Hook / Tagline</span></label>
			{{-- NEW: AI generation button --}}
			<button type="button" class="btn btn-ghost btn-xs generate-hook-btn">
				<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></svg>
				Generate with AI
			</button>
		</div>
		<textarea name="hook" class="textarea textarea-bordered w-full" rows="2">{{ old('hook', $book?->hook) }}</textarea>
		@error('hook')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
	</div>
	
	{{-- About the Book --}}
	<div>
		<div class="flex justify-between items-center">
			<label class="label"><span class="label-text">About the Book</span></label>
			{{-- NEW: AI generation button --}}
			<button type="button" class="btn btn-ghost btn-xs generate-about-btn">
				<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></svg>
				Generate with AI
			</button>
		</div>
		<textarea name="about" class="textarea textarea-bordered w-full" rows="4">{{ old('about', $book?->about) }}</textarea>
		@error('about')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
	</div>
	
	{{-- NEW: Added Extract field --}}
	<div>
		<label class="label"><span class="label-text">Longer Extract (e.g., First Chapter)</span></label>
		<textarea name="extract" class="textarea textarea-bordered w-full" rows="8">{{ old('extract', $book?->extract) }}</textarea>
		@error('extract')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
	</div>
	
	{{-- NEW: Added Links and Date fields --}}
	<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
		<div>
			<label class="label"><span class="label-text">Amazon Link</span></label>
			<input type="url" name="amazon_link" class="input input-bordered w-full" placeholder="https://" value="{{ old('amazon_link', $book?->amazon_link) }}" />
			@error('amazon_link')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
		</div>
		<div>
			<label class="label"><span class="label-text">Other Link</span></label>
			<input type="url" name="other_link" class="input input-bordered w-full" placeholder="https://" value="{{ old('other_link', $book?->other_link) }}" />
			@error('other_link')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
		</div>
		<div>
			<label class="label"><span class="label-text">Publishing Date</span></label>
			<input type="date" name="published_at" class="input input-bordered w-full" value="{{ old('published_at', $book?->published_at?->format('Y-m-d')) }}" />
			@error('published_at')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
		</div>
	</div>
	
	{{-- NEW: Added Series Info fields --}}
	<div class="space-y-4 rounded-md border p-4 bg-base-200/50">
		<div class="form-control">
			<label class="label cursor-pointer justify-start gap-2">
				<input type="checkbox" name="is_series" value="1" class="checkbox is-series-checkbox" @checked(old('is_series', $book?->series_name)) />
				<span class="label-text font-medium">Part of a series?</span>
			</label>
		</div>
		<div class="series-fields-container grid-cols-1 md:grid-cols-2 gap-4 pl-6" style="display: none;">
			<div>
				<label class="label"><span class="label-text">Series Name *</span></label>
				<input type="text" name="series_name" class="input input-bordered w-full" value="{{ old('series_name', $book?->series_name) }}" />
				@error('series_name')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
			</div>
			<div>
				<label class="label"><span class="label-text">Book Number *</span></label>
				<input type="number" min="1" name="series_number" class="input input-bordered w-full" value="{{ old('series_number', $book?->series_number) }}" />
				@error('series_number')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
			</div>
		</div>
	</div>
	
	{{-- Actions --}}
	<div class="modal-action">
		<button type="submit" class="btn btn-primary">{{ $isNew ? 'Add Book' : 'Save Changes' }}</button>
		<form method="dialog"><button class="btn">Close</button></form>
	</div>
</form>
