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
	<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
	
	{{-- ADDED: Theme controller script to prevent FOUC --}}
	<script>
		// On page load or when changing themes, best to add inline in `head` to avoid FOUC
		if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
			document.documentElement.setAttribute('data-theme', 'dark');
		} else {
			document.documentElement.setAttribute('data-theme', 'light');
		}
	</script>
	
	<!-- Scripts -->
	@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 antialiased">
<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-base-200 relative">
	{{-- ADDED: Theme switcher for guest pages --}}
	<div class="absolute top-4 right-4">
		<label class="flex cursor-pointer gap-2 items-center">
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/></svg>
			<input type="checkbox" value="dark" id="theme-controller" class="toggle theme-controller"/>
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
		</label>
	</div>
	
	<div>
		<a href="/">
			<h1 class="text-3xl font-bold">{{ config('app.name', 'Laravel') }}</h1>
		</a>
	</div>
	
	<div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-base-100 shadow-md overflow-hidden sm:rounded-lg">
		{{-- The @yield directive specifies where content from a child page will be injected. --}}
		@yield('content')
	</div>
</div>
{{-- ADDED: Script to manage theme controller state --}}
<script>
	document.addEventListener('DOMContentLoaded', function () {
		const themeController = document.getElementById('theme-controller');
		if (themeController) {
			// Set the checkbox state on page load
			if (localStorage.getItem('theme') === 'dark') {
				themeController.checked = true;
			}
			
			// When the checkbox is clicked, save the new theme to localStorage
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
