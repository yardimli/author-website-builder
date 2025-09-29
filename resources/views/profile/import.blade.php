@extends('layouts.app')

@section('content')
	<div class="py-12">
		<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
			<div class="p-4 sm:p-8 bg-base-100 shadow sm:rounded-lg">
				<section>
					<header>
						<h2 class="text-lg font-medium text-base-content">Import from BookCoverZone</h2>
						<p class="mt-1 text-sm text-base-content/70">
							Fetch your rendered book covers from your BookCoverZone account to add them to your profile here.
						</p>
					</header>
					
					<div class="mt-6">
						<button id="fetch-books-btn" class="btn btn-primary">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
							Fetch My Books
						</button>
					</div>
					
					<div id="import-status" class="mt-6"></div>
					
					<div id="book-list-container" class="mt-6 space-y-4">
						{{-- Book list will be dynamically inserted here --}}
					</div>
				</section>
			</div>
		</div>
	</div>
@endsection

@push('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const fetchBtn = document.getElementById('fetch-books-btn');
			const statusDiv = document.getElementById('import-status');
			const bookListContainer = document.getElementById('book-list-container');
			const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
			let allBooks = [];
			
			const renderStatus = (message, isError = false) => {
				statusDiv.innerHTML = `
			<div role="alert" class="alert ${isError ? 'alert-error' : 'alert-info'}">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
				<span>${message}</span>
			</div>
		`;
			};
			
			const renderBookList = (books) => {
				if (books.length === 0) {
					renderStatus('No book covers found in your BookCoverZone account.', false);
					return;
				}
				
				bookListContainer.innerHTML = books.map((book, index) => `
			<div class="card card-side bg-base-200 shadow-md flex-col sm:flex-row" id="book-card-${index}">
				<figure class="p-4 flex-shrink-0 sm:w-32 md:w-40 flex justify-center">
					<img src="${book.front_cover_url}" alt="${book.title} cover" class="w-28 h-42 object-cover rounded" />
				</figure>
				<div class="card-body">
					<h3 class="card-title">${book.title}</h3>
					<p class="text-sm opacity-80">Rendered: ${new Date(book.render_date).toLocaleDateString()}</p>
					<p class="text-sm opacity-80">Trim: ${book.trim_size_name}</p>
					<div>
						${book.is_purchased ? '<div class="badge badge-success">Purchased</div>' : '<div class="badge badge-ghost">Evaluation</div>'}
						${book.has_back_cover ? '<div class="badge badge-info ml-2">Back Cover</div>' : ''}
					</div>
					
					${book.has_back_cover ? `
					<div class="form-control mt-4">
						<label class="label cursor-pointer justify-start gap-2">
							<input type="checkbox" id="update-profile-${index}" class="checkbox checkbox-sm" />
							<span class="label-text">Update my bio & photo from this cover</span>
						</label>
					</div>
					` : ''}

					<div class="card-actions justify-end mt-2">
						<button class="btn btn-primary btn-sm import-btn" data-index="${index}">Import</button>
					</div>
				</div>
			</div>
		`).join('');
			};
			
			fetchBtn.addEventListener('click', async () => {
				fetchBtn.classList.add('btn-disabled');
				fetchBtn.innerHTML = '<span class="loading loading-spinner"></span> Fetching...';
				statusDiv.innerHTML = '';
				bookListContainer.innerHTML = '';
				
				try {
					const response = await fetch('{{ route("profile.import.fetch") }}', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							'Accept': 'application/json',
						},
					});
					
					const data = await response.json();
					
					if (!response.ok || !data.success) {
						throw new Error(data.message || 'Failed to fetch books.');
					}
					
					allBooks = data.books;
					renderStatus(`Found ${allBooks.length} book renders.`, false);
					renderBookList(allBooks);
					
				} catch (error) {
					console.error('Fetch error:', error);
					renderStatus(error.message, true);
				} finally {
					fetchBtn.classList.remove('btn-disabled');
					fetchBtn.innerHTML = 'Fetch My Books';
				}
			});
			
			bookListContainer.addEventListener('click', async (e) => {
				if (!e.target.classList.contains('import-btn')) {
					return;
				}
				
				const importBtn = e.target;
				const index = importBtn.dataset.index;
				const bookToImport = allBooks[index];
				const updateProfileCheckbox = document.getElementById(`update-profile-${index}`);
				const shouldUpdateProfile = updateProfileCheckbox ? updateProfileCheckbox.checked : false;
				
				importBtn.classList.add('btn-disabled');
				importBtn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Importing...';
				
				try {
					const response = await fetch('{{ route("profile.import.store") }}', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							'Accept': 'application/json',
						},
						body: JSON.stringify({
							bookData: bookToImport,
							updateProfile: shouldUpdateProfile,
						}),
					});
					
					const data = await response.json();
					
					if (!response.ok || !data.success) {
						throw new Error(data.message || 'Failed to import book.');
					}
					
					const card = document.getElementById(`book-card-${index}`);
					card.classList.add('opacity-50');
					importBtn.classList.remove('btn-primary');
					importBtn.classList.add('btn-success');
					importBtn.innerHTML = 'Imported!';
					
				} catch (error) {
					console.error('Import error:', error);
					alert(`Import failed: ${error.message}`);
					importBtn.classList.remove('btn-disabled');
					importBtn.innerHTML = 'Import';
				}
			});
		});
	</script>
@endpush
