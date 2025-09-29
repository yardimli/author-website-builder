@extends('layouts.app')

@section('content')
	<div class="py-12">
		<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
			<div class="p-4 sm:p-8 bg-base-100 shadow sm:rounded-lg">
				{{-- This view now contains only the account deletion form --}}
				@include('profile.partials.delete-user-form')
			</div>
		</div>
	</div>
@endsection
