<section>
	<header class="flex justify-between items-center">
		<div>
			<h2 class="text-lg font-medium text-base-content">Your Books</h2>
			<p class="mt-1 text-sm text-base-content/70">
				Manage the books you want to showcase.
			</p>
		</div>
		{{-- Button to open the modal for adding a new book --}}
		<button class="btn btn-primary" onclick="add_book_modal.showModal()">Add New Book</button>
	</header>
	
	{{-- List of existing books --}}
	<div class="mt-6 space-y-6">
		@forelse($books as $book)
			<div class="card card-side bg-base-200 shadow-md">
				<figure class="p-4 flex-shrink-0">
					<img src="{{ $book->cover_image_url ?? 'https://via.placeholder.com/150x220.png/EFEFEF/AAAAAA?text=No+Cover' }}" alt="{{ $book->title }} cover" class="w-24 h-36 object-cover rounded" />
				</figure>
				<div class="card-body">
					<h3 class="card-title">{{ $book->title }}</h3>
					<p>{{ $book->subtitle }}</p>
					@if($book->series_name)
						<p class="text-xs opacity-70">({{ $book->series_name }}, Book {{ $book->series_number }})</p>
					@endif
					<div class="card-actions justify-end">
						{{-- Note: Edit functionality would require a separate modal or page --}}
						<form method="POST" action="{{ route('profile.books.destroy', $book) }}" onsubmit="return confirm('Are you sure you want to delete this book?');">
							@csrf
							@method('DELETE')
							<button type="submit" class="btn btn-error btn-sm">Delete</button>
						</form>
					</div>
				</div>
			</div>
		@empty
			<p class="text-center text-base-content/70 py-4">You haven't added any books yet.</p>
		@endforelse
	</div>
	
	<!-- Add Book Modal using DaisyUI modal component -->
	<dialog id="add_book_modal" class="modal">
		<div class="modal-box w-11/12 max-w-5xl">
			<h3 class="font-bold text-lg">Add a New Book</h3>
			<form method="POST" action="{{ route('profile.books.store') }}" class="py-4 space-y-4" enctype="multipart/form-data">
				@csrf
				{{-- Title and Subtitle --}}
				<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
					<div>
						<label class="label"><span class="label-text">Title *</span></label>
						<input type="text" name="title" class="input input-bordered w-full" required />
					</div>
					<div>
						<label class="label"><span class="label-text">Subtitle</span></label>
						<input type="text" name="subtitle" class="input input-bordered w-full" />
					</div>
				</div>
				{{-- Hook and About --}}
				<div>
					<label class="label"><span class="label-text">Hook / Tagline</span></label>
					<textarea name="hook" class="textarea textarea-bordered w-full" rows="2"></textarea>
				</div>
				<div>
					<label class="label"><span class="label-text">About the Book</span></label>
					<textarea name="about" class="textarea textarea-bordered w-full" rows="4"></textarea>
				</div>
				{{-- Cover Image --}}
				<div>
					<label class="label"><span class="label-text">Cover Image</span></label>
					<input type="file" name="cover_image" class="file-input file-input-bordered w-full" accept="image/*" />
				</div>
				{{-- Add other fields like amazon_link, published_at, series info as needed --}}
				<div class="modal-action">
					<button type="submit" class="btn btn-primary">Add Book</button>
					<form method="dialog">
						<button class="btn">Close</button>
					</form>
				</div>
			</form>
		</div>
	</dialog>
</section>
