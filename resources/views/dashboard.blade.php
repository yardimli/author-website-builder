@extends('layouts.app')

@section('content')
	<div class="py-12">
		<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
			<div class="bg-base-100 overflow-hidden shadow-sm sm:rounded-lg">
				<div class="p-6 text-base-content">
					
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
											<li>Added at least one book to your profile. <a href="{{ route('profile.edit') }}" class="font-semibold underline">Go to Profile</a></li>
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
												<a href="{{ route('websites.show', $website->id) }}">{{ $website->name }}</a>
											</h2>
											{{-- MODIFIED: Replaced JavaScript method with PHP Carbon method --}}
											<p>Created: {{ $website->created_at->toFormattedDateString() }}</p>
											<div class="card-actions justify-end">
												<a href="{{ route('websites.show', $website->id) }}" class="btn btn-outline btn-sm">Open Editor</a>
											</div>
										</div>
									</div>
								@endforeach
							</div>
						</div>
					@endif
				
				</div>
			</div>
		</div>
	</div>
@endsection
