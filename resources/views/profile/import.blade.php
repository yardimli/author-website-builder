@extends('layouts.app')

@section('content')
	<div class="py-12">
		<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
			<div class="bg-base-100 overflow-hidden shadow-sm sm:rounded-lg">
				<div class="p-6 md:p-8 text-base-content">
					<header class="flex flex-wrap justify-between items-center gap-4 border-b border-base-300 pb-4 mb-6">
						<div>
							<h2 class="text-xl font-bold text-base-content">Import Books from BookCoverZone</h2>
							<p class="mt-1 text-sm text-base-content/70">
								Import previously created book covers. The latest render for each cover size is shown.
							</p>
						</div>
						<a href="{{ route('profile.books.edit') }}" class="btn btn-ghost">&larr; Back to My Books</a>
					</header>
					
					{{-- Search and Filters --}}
					<div class="mb-6">
						<input type="text" id="search-input" placeholder="Search by title..." class="input input-bordered w-full max-w-xs">
					</div>
					
					{{-- Results Container --}}
					<div id="results-container" class="space-y-4">
						{{-- Loading state --}}
						<div id="loading-indicator" class="text-center py-8">
							<span class="loading loading-lg loading-spinner"></span>
							<p>Fetching your books...</p>
						</div>
						{{-- Content will be injected here --}}
					</div>
					
					{{-- Pagination Container --}}
					<div id="pagination-container" class="mt-6">
						{{-- Pagination links will be injected here --}}
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const searchInput = document.getElementById('search-input');
			const resultsContainer = document.getElementById('results-container');
			const paginationContainer = document.getElementById('pagination-container');
			const loadingIndicator = document.getElementById('loading-indicator');
			const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
			let debounceTimer;
			
			const fetchBooks = async (page = 1, search = '') => {
				loadingIndicator.style.display = 'block';
				resultsContainer.innerHTML = ''; // Clear previous results
				paginationContainer.innerHTML = '';
				
				try {
					const url = new URL("{{ route('profile.import.fetch') }}");
					url.searchParams.append('page', page);
					if (search) {
						url.searchParams.append('search', search);
					}
					
					const response = await fetch(url, {
						method: 'POST', // Using POST to avoid query string issues with some servers/proxies
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							'Accept': 'application/json',
						},
						body: JSON.stringify({ search: search }) // Send search in body as well
					});
					
					if (!response.ok) {
						const errorData = await response.json();
						throw new Error(errorData.message || 'Failed to fetch books.');
					}
					
					const result = await response.json();
					
					if (result.success) {
						renderBooks(result.books.data);
						renderPagination(result.books);
					} else {
						throw new Error(result.message);
					}
					
				} catch (error) {
					resultsContainer.innerHTML = `<div class="alert alert-error">Error: ${error.message}</div>`;
				} finally {
					loadingIndicator.style.display = 'none';
				}
			};
			
			const renderBooks = (books) => {
				if (books.length === 0) {
					resultsContainer.innerHTML = `<p class="text-center text-base-content/70 py-4">No books found.</p>`;
					return;
				}
				
				resultsContainer.innerHTML = books.map(book => `
            <div class="card card-side bg-base-200 shadow-md flex-col sm:flex-row" id="book-card-${book.front_history_id}">
                <figure class="p-4 flex-shrink-0 sm:w-32 md:w-40 flex justify-center">
                    <img src="${book.front_cover_url}" alt="${book.title} cover" class="w-28 h-42 object-cover rounded" />
                </figure>
                <div class="card-body">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="card-title">${book.title}</h3>
                            <p class="text-sm opacity-80">${book.author}</p>
                            <div class="text-xs opacity-70 mt-2 space-y-1">
                                <p><strong>Size:</strong> ${book.trim_size_name}</p>
                                <p><strong>Rendered:</strong> ${new Date(book.render_date).toLocaleDateString()}</p>
                                ${book.is_purchased ? '<span class="badge badge-success badge-sm">Purchased</span>' : ''}
                                ${book.has_back_cover ? '<span class="badge badge-info badge-sm">Has Back Cover</span>' : ''}
                            </div>
                        </div>
                        <div class="card-actions justify-end flex-shrink-0 ml-2">
                             <button class="btn btn-primary btn-sm import-btn" data-book='${JSON.stringify(book)}'>
                                Import
                            </button>
                        </div>
                    </div>
                    ${book.has_back_cover ? `
                    <div class="form-control mt-4">
                        <label class="label cursor-pointer justify-start gap-2">
                            <input type="checkbox" class="checkbox checkbox-sm update-profile-checkbox" checked />
                            <span class="label-text text-xs">Update profile bio & photo from this back cover?</span>
                        </label>
                    </div>
                    ` : ''}
                </div>
            </div>
        `).join('');
			};
			
			const renderPagination = (paginator) => {
				if (!paginator || paginator.links.length <= 3) {
					paginationContainer.innerHTML = '';
					return;
				}
				
				paginationContainer.innerHTML = `
            <div class="join">
                ${paginator.links.map(link => `
                    <button
                        class="join-item btn ${link.active ? 'btn-active' : ''} ${!link.url ? 'btn-disabled' : ''}"
                        data-url="${link.url}"
                        ${!link.url ? 'disabled' : ''}
                    >
                        ${link.label.replace('&laquo;', '«').replace('&raquo;', '»')}
                    </button>
                `).join('')}
            </div>
        `;
			};
			
			const importBook = async (button) => {
				const bookData = JSON.parse(button.dataset.book);
				const card = document.getElementById(`book-card-${bookData.front_history_id}`);
				const updateProfileCheckbox = card.querySelector('.update-profile-checkbox');
				const updateProfile = updateProfileCheckbox ? updateProfileCheckbox.checked : false;
				
				button.disabled = true;
				button.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Importing...';
				
				try {
					const response = await fetch("{{ route('profile.import.store') }}", {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							'Accept': 'application/json',
						},
						body: JSON.stringify({ bookData, updateProfile }),
					});
					
					const result = await response.json();
					
					if (!response.ok || !result.success) {
						throw new Error(result.message || 'Failed to import book.');
					}
					
					card.classList.remove('bg-base-200');
					card.classList.add('bg-success', 'text-success-content');
					card.innerHTML = '<div class="card-body text-center">Imported successfully!</div>';
					
				} catch (error) {
					alert(`Import Error: ${error.message}`);
					button.disabled = false;
					button.innerHTML = 'Import';
				}
			};
			
			// --- Event Listeners ---
			
			searchInput.addEventListener('input', () => {
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(() => {
					fetchBooks(1, searchInput.value.trim());
				}, 500); // 500ms debounce
			});
			
			paginationContainer.addEventListener('click', (e) => {
				const button = e.target.closest('button');
				if (button && button.dataset.url) {
					const url = new URL(button.dataset.url);
					const page = url.searchParams.get('page');
					fetchBooks(page, searchInput.value.trim());
				}
			});
			
			resultsContainer.addEventListener('click', (e) => {
				const button = e.target.closest('.import-btn');
				if (button) {
					importBook(button);
				}
			});
			
			// --- Initial Load ---
			fetchBooks();
		});
	</script>
@endpush
