@extends('layouts.app')

@section('content')
	<div class="py-12">
		<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
			{{-- MODIFIED: Removed the tabbed interface. This page now only shows core profile forms. --}}
			<div class="p-4 sm:p-8 bg-base-100 shadow sm:rounded-lg">
				@include('profile.partials.update-profile-photo-form')
			</div>
			
			<div class="p-4 sm:p-8 bg-base-100 shadow sm:rounded-lg">
				@include('profile.partials.update-profile-information-form')
			</div>
			
			<div class="p-4 sm:p-8 bg-base-100 shadow sm:rounded-lg">
				@include('profile.partials.update-bio-form')
			</div>
		</div>
	</div>
@endsection
