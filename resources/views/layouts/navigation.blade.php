{{-- MODIFIED: Complete restructure for top-bar navigation --}}
<nav class="navbar bg-base-100 shadow-sm sticky top-0 z-30">
	<div class="navbar-start">
		{{-- Mobile Nav Dropdown --}}
		<div class="dropdown">
			<div tabindex="0" role="button" class="btn btn-ghost sm:hidden">
				<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" /></svg>
			</div>
			<ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 rounded-box w-52">
				<li><a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a></li>
				<li><a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.edit') ? 'active' : '' }}">Profile</a></li>
			</ul>
		</div>
		
		{{-- App Logo/Name --}}
		<a href="{{ route('dashboard') }}" class="btn btn-ghost text-xl">
			{{ config('app.name', 'Laravel') }}
		</a>
		
		{{-- Top Menu Links (Desktop) --}}
		<div class="hidden sm:flex">
			<ul class="menu menu-horizontal px-1">
				<li>
					<a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
						Dashboard
					</a>
				</li>
				<li>
					<a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.edit') ? 'active' : '' }}">
						Profile
					</a>
				</li>
			</ul>
		</div>
	</div>
	
	<div class="navbar-end">
		{{-- ADDED: Theme Switcher Toggle --}}
		<label class="flex cursor-pointer gap-2 items-center mr-4">
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/></svg>
			<input type="checkbox" value="dark" id="theme-controller" class="toggle theme-controller"/>
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
		</label>
		
		{{-- User dropdown menu --}}
		<div class="dropdown dropdown-end">
			<div tabindex="0" role="button" class="btn btn-ghost">
				<div>{{ Auth::user()->name }}</div>
				<div class="ml-1">
					<svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
						<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
					</svg>
				</div>
			</div>
			<ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 rounded-box w-52">
				<li><a href="{{ route('profile.edit') }}">{{ __('Profile') }}</a></li>
				<li>
					<!-- Authentication -->
					<form method="POST" action="{{ route('logout') }}">
						@csrf
						<a href="{{ route('logout') }}"
						   onclick="event.preventDefault(); this.closest('form').submit();">
							{{ __('Log Out') }}
						</a>
					</form>
				</li>
			</ul>
		</div>
	</div>
</nav>
