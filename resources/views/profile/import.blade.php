@extends('layouts.app')

@section('content')
	<div class="py-12">
		<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
			<div class="p-4 sm:p-8 bg-base-100 shadow sm:rounded-lg">
				<header class="flex justify-between items-center">
					<div>
						<h2 class="text-lg font-medium text-base-content">Import from BookCoverZone</h2>
						<p class="mt-1 text-sm text-base-content/70">
							Search for and import your previously created book covers and data.
						</p>
					</div>
					<a href="{{ route('profile.books.edit') }}" class="btn btn-ghost">&larr; Back to Books</a>
				</header>
				
				{{-- Search Input --}}
				<div class="mt-6">
					<input type="text" id="search-input" placeholder="Search by title, author, or cover ID..." class="input input-bordered w-full max-w-lg" />
				</div>
				
				{{-- Results Container --}}
				<div id="books-container" class="mt-6 space-y-4">
					{{-- Books will be dynamically inserted here --}}
				</div>
				
				{{-- Loading Indicator --}}
				<div id="loading-indicator" class="text-center py-10" style="display: none;">
					<span class="loading loading-lg loading-spinner"></span>
				</div>
				
				{{-- Pagination --}}
				<div id="pagination-container" class="mt-6">
					{{-- Pagination links will be dynamically inserted here --}}
				</div>
			</div>
		</div>
	</div>
	
	{{-- Import Confirmation Modal --}}
	<dialog id="import_confirm_modal" class="modal">
		<div class="modal-box">
			<h3 class="font-bold text-lg">Confirm Import</h3>
			<div id="import-details" class="py-4 space-y-2">
				{{-- Details will be injected here --}}
			</div>
			<div class="form-control">
				<label class="label cursor-pointer justify-start gap-2">
					<input type="checkbox" id="update-profile-checkbox" checked="checked" class="checkbox" />
					<span class="label-text">Update profile with author bio & photo?</span>
				</label>
			</div>
			<div class="modal-action">
				<form method="dialog">
					<button class="btn">Cancel</button>
				</form>
				<button id="confirm-import-btn" class="btn btn-primary">Yes, Import</button>
			</div>
		</div>
		<form method="dialog" class="modal-backdrop"><button>close</button></form>
	</dialog>
@endsection

@push('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const searchInput = document.getElementById('search-input');
			const booksContainer = document.getElementById('books-container');
			const loadingIndicator = document.getElementById('loading-indicator');
			const paginationContainer = document.getElementById('pagination-container');
			const importModal = document.getElementById('import_confirm_modal');
			const confirmImportBtn = document.getElementById('confirm-import-btn');
			const importDetailsContainer = document.getElementById('import-details');
			const updateProfileCheckbox = document.getElementById('update-profile-checkbox');
			
			const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
			let debounceTimer;
			let currentImportData = null;
			
			const fetchBooks = async (url = "{{ route('profile.import.fetch') }}") => {
				loadingIndicator.style.display = 'block';
				booksContainer.innerHTML = '';
				paginationContainer.innerHTML = '';
				
				try {
					const response = await fetch(url, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							'Accept': 'application/json',
						},
						body: JSON.stringify({ search: searchInput.value }),
					});
					
					if (!response.ok) {
						const errorData = await response.json();
						throw new Error(errorData.message || 'Failed to fetch books.');
					}
					
					const data = await response.json();
					renderBooks(data.books.data);
					renderPagination(data.books);
					
				} catch (error) {
					console.error('Fetch error:', error);
					booksContainer.innerHTML = `<div class="alert alert-error">Error: ${error.message}</div>`;
				} finally {
					loadingIndicator.style.display = 'none';
				}
			};
			
			const renderBooks = (books) => {
				if (books.length === 0) {
					booksContainer.innerHTML = '<p class="text-center text-base-content/70 py-4">No books found.</p>';
					return;
				}
				
				let html = '';
				books.forEach(book => {
					const escapedBookData = JSON.stringify(book).replace(/'/g, '&#39;');
					
					// MODIFIED: Added a block to display render and purchase dates.
					html += `
                        <div class="card card-side bg-base-200 shadow-md flex-col sm:flex-row">
                            <figure class="p-4 flex-shrink-0 sm:w-32 md:w-40 flex justify-center">
                                <img src="${book.front_cover_url}" alt="${book.title} cover" class="w-28 h-42 object-cover rounded" />
                            </figure>
                            <div class="card-body">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="card-title">${book.title}</h3>
                                        <p class="text-sm opacity-80">${book.author || ''}</p>
                                        
                                        <!-- NEW: Added render and purchase dates -->
                                        <div class="mt-2 text-xs opacity-70">
                                            <p>Rendered: ${new Date(book.render_date).toLocaleDateString()}</p>
                                            ${book.purchase_date ? `<p>Purchased: ${new Date(book.purchase_date).toLocaleDateString()}</p>` : ''}
                                        </div>
                                        
                                        <div class="mt-2 space-x-2">
                                            ${book.is_purchased ? '<div class="badge badge-success">Purchased</div>' : ''}
                                            ${book.has_back_cover ? '<div class="badge badge-info">Has Back Cover</div>' : ''}
                                        </div>
                                    </div>
                                    <div class="card-actions justify-end flex-shrink-0 ml-2">
                                        <button class="btn btn-primary btn-sm import-btn" data-book='${escapedBookData}'>
                                            Import
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
				});
				booksContainer.innerHTML = html;
			};
			
			const renderPagination = (paginator) => {
				if (!paginator || paginator.links.length <= 3) {
					paginationContainer.innerHTML = '';
					return;
				}
				
				let html = '<div class="join">';
				paginator.links.forEach(link => {
					html += `
                        <button class="join-item btn ${link.active ? 'btn-active' : ''} ${!link.url ? 'btn-disabled' : ''}" data-url="${link.url}">
                            ${link.label}
                        </button>
                    `;
				});
				html += '</div>';
				paginationContainer.innerHTML = html;
			};
			
			const handleImportClick = (bookData) => {
				currentImportData = bookData;
				let detailsHtml = `<p>You are about to import the book: <strong>${bookData.title}</strong>.</p>`;
				if (bookData.author_bio) {
					detailsHtml += `<p class="text-sm mt-2"><strong>Bio found:</strong> "${bookData.author_bio.substring(0, 100)}..."</p>`;
				}
				if (bookData.author_photo_url) {
					detailsHtml += `<div class="mt-2 flex items-center gap-2"><strong class="text-sm">Photo found:</strong> <img src="${bookData.author_photo_url}" class="w-10 h-10 rounded-full object-cover" /></div>`;
				}
				importDetailsContainer.innerHTML = detailsHtml;
				importModal.showModal();
			};
			
			const executeImport = async () => {
				if (!currentImportData) return;
				
				confirmImportBtn.classList.add('btn-disabled', 'loading');
				
				try {
					const response = await fetch("{{ route('profile.import.store') }}", {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							'Accept': 'application/json',
						},
						body: JSON.stringify({
							bookData: currentImportData,
							updateProfile: updateProfileCheckbox.checked,
						}),
					});
					
					const data = await response.json();
					
					if (!response.ok) {
						throw new Error(data.message || 'Failed to import book.');
					}
					
					importModal.close();
					alert('Book imported successfully!');
					
				} catch (error) {
					console.error('Import error:', error);
					alert('Error: ' + error.message);
				} finally {
					confirmImportBtn.classList.remove('btn-disabled', 'loading');
					currentImportData = null;
				}
			};
			
			// --- Event Listeners ---
			
			searchInput.addEventListener('input', () => {
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(() => fetchBooks(), 500);
			});
			
			paginationContainer.addEventListener('click', (e) => {
				const button = e.target.closest('button[data-url]');
				if (button && button.dataset.url !== 'null') {
					fetchBooks(button.dataset.url);
				}
			});
			
			booksContainer.addEventListener('click', (e) => {
				const button = e.target.closest('.import-btn');
				if (button) {
					const bookData = JSON.parse(button.dataset.book);
					handleImportClick(bookData);
				}
			});
			
			confirmImportBtn.addEventListener('click', executeImport);
			
			// --- Initial Load ---
			fetchBooks();
		});
	</script>
@endpush
