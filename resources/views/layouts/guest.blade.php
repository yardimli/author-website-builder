<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	
	<title>{{ config('app.name', 'Laravel') }}</title>
	
	<!-- Fonts -->
	<link rel="preconnect" href="https://fonts.bunny.net">
	<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
	
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
<body class="font-sans antialiased">
<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-base-200 relative">
	<div class="absolute top-4 right-4">
		<label class="swap swap-rotate btn btn-ghost btn-circle">
			{{-- MODIFIED: Added 'hidden' class to prevent the checkbox from being visible --}}
			<input type="checkbox" id="theme-controller" class="hidden" />
			
			{{-- Sun icon --}}
			<svg class="swap-off fill-current w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7.05a1,1,0,0,0,.7.29,1,1,0,0,0,.71-.29l.71-.71a1,1,0,0,0-1.41-1.41l-.71.71A1,1,0,0,0,5.64,7.05ZM18.36,17A1,1,0,0,0,17,18.36l.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41ZM20,12a1,1,0,0,0-1-1H18a1,1,0,0,0,0,2h1A1,1,0,0,0,20,12ZM17,5.64a1,1,0,0,0,.71-.29l.71-.71a1,1,0,1,0-1.41-1.41l-.71.71A1,1,0,0,0,17,5.64ZM12,15a3,3,0,1,0,0-6A3,3,0,0,0,12,15Zm0,2a5,5,0,1,0,0-10A5,5,0,0,0,12,17Z"/></svg>
			
			{{-- Moon icon --}}
			<svg class="swap-on fill-current w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73A8.15,8.15,0,0,1,9.08,5.49a8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22a10.14,10.14,0,0,0,9.5,14.49A8.14,8.14,0,0,1,12.14,19.69Z"/></svg>
		</label>
	</div>
	
	<div>
		<a href="/">
			<h1 class="text-3xl font-bold">{{ config('app.name', 'Laravel') }}</h1>
		</a>
	</div>
	
	<div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-base-100 shadow-md overflow-hidden sm:rounded-lg">
		@yield('content')
	</div>
</div>
<script>
	document.addEventListener('DOMContentLoaded', function () {
		const themeController = document.getElementById('theme-controller');
		if (themeController) {
			// Set the checkbox state on page load to match the current theme
			if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
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
