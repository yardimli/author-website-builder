@extends('layouts.app')

@section('content')
	<div class="py-12">
		<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
			<div class="p-4 sm:p-8 bg-base-100 shadow sm:rounded-lg">
				{{-- This view now contains only the book management form --}}
				@include('profile.partials.manage-books-form')
			</div>
		</div>
	</div>
@endsection
