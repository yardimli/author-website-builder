@extends('layouts.app')

@section('content')
	<div class="py-12">
		<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
			{{-- DaisyUI Tabs for organizing profile sections --}}
			<div role="tablist" class="tabs tabs-lifted">
				
				{{-- Profile Tab --}}
				<input type="radio" name="profile_tabs" role="tab" class="tab" aria-label="Profile" checked />
				<div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6">
					<div class="space-y-6">
						<div class="p-4 sm:p-8 bg-base-200 shadow sm:rounded-lg">
							@include('profile.partials.update-profile-photo-form')
						</div>
						<div class="p-4 sm:p-8 bg-base-200 shadow sm:rounded-lg">
							@include('profile.partials.update-profile-information-form')
						</div>
						<div class="p-4 sm:p-8 bg-base-200 shadow sm:rounded-lg">
							@include('profile.partials.update-bio-form')
						</div>
					</div>
				</div>
				
				{{-- Books Tab --}}
				<input type="radio" name="profile_tabs" role="tab" class="tab" aria-label="Books" />
				<div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6">
					@include('profile.partials.manage-books-form')
				</div>
				
				{{-- Security Tab --}}
				<input type="radio" name="profile_tabs" role="tab" class="tab" aria-label="Security" />
				<div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6">
					@include('profile.partials.update-password-form')
				</div>
				
				{{-- Account Tab --}}
				<input type="radio" name="profile_tabs" role="tab" class="tab" aria-label="Account" />
				<div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6">
					@include('profile.partials.delete-user-form')
				</div>
			</div>
		</div>
	</div>
@endsection
