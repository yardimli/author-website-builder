<!DOCTYPE html>
{{-- MODIFIED: data-theme is now managed by script --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	
	<title>{{ config('app.name', 'Laravel') }}</title>
	
	<!-- Fonts -->
	<link rel="preconnect" href="https://fonts.bunny.net">
	<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet"/>
	
	{{-- ADDED: Theme controller script to prevent FOUC (Flash of Unstyled Content) --}}
	<script>
		// On page load or when changing themes, it's best to add this inline in `head` to avoid FOUC
		if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
			document.documentElement.setAttribute('data-theme', 'dark');
		} else {
			document.documentElement.setAttribute('data-theme', 'light');
		}
	</script>
	
	<!-- Scripts & Styles -->
	@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
{{-- MODIFIED: Removed drawer layout for a simpler top-nav layout --}}
<div class="min-h-screen bg-base-200">
	@include('layouts.navigation')
	
	<!-- Page Content -->
	<main>
		{{-- The @yield directive specifies where content from a child page will be injected. --}}
		@yield('content')
	</main>
</div>
@stack('scripts')
{{-- ADDED: Script to manage theme controller state persistence --}}
<script>
	document.addEventListener('DOMContentLoaded', function () {
		const themeController = document.getElementById('theme-controller');
		if (themeController) {
			// Set the checkbox state on page load based on what's in localStorage
			if (localStorage.getItem('theme') === 'dark') {
				themeController.checked = true;
			}
			
			// When the toggle is clicked, update the theme and save the preference
			themeController.addEventListener('change', function () {
				if (this.checked) {
					document.documentElement.setAttribute('data-theme', 'dark');
					localStorage.setItem('theme', 'dark');
				} else {
					document.documentElement.setAttribute('data-theme', 'light');
					localStorage.setItem('theme', 'light');
				}
			});
		}
	});
</script>
</body>
</html>
