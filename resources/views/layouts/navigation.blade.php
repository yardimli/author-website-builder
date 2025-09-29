{{-- MODIFIED: Complete restructure for top-bar navigation --}}
<nav class="navbar bg-base-100 shadow-sm sticky top-0 z-30">
	<div class="navbar-start">
		{{-- Mobile Nav Dropdown --}}
		<div class="dropdown">
			<div tabindex="0" role="button" class="btn btn-ghost sm:hidden">
				<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" /></svg>
			</div>
			{{-- MODIFIED: Added new links to the mobile dropdown menu --}}
			<ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 rounded-box w-52">
				<li><a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a></li>
				<li><a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.edit') ? 'active' : '' }}">Profile</a></li>
				<li><a href="{{ route('profile.books.edit') }}" class="{{ request()->routeIs('profile.books.edit') ? 'active' : '' }}">Books</a></li>
				<li><a href="{{ route('profile.security.edit') }}" class="{{ request()->routeIs('profile.security.edit') ? 'active' : '' }}">Security</a></li>
				<li><a href="{{ route('profile.account.edit') }}" class="{{ request()->routeIs('profile.account.edit') ? 'active' : '' }}">Account</a></li>
			</ul>
		</div>
		
		{{-- App Logo/Name --}}
		<a href="{{ route('dashboard') }}" class="btn btn-ghost text-xl">
			{{ config('app.name', 'Laravel') }}
		</a>
		
		{{-- Top Menu Links (Desktop) --}}
		<div class="hidden sm:flex">
			{{-- MODIFIED: Added new links to the desktop menu --}}
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
				<li>
					<a href="{{ route('profile.books.edit') }}" class="{{ request()->routeIs('profile.books.edit') ? 'active' : '' }}">
						Books
					</a>
				</li>
				<li>
					<a href="{{ route('profile.security.edit') }}" class="{{ request()->routeIs('profile.security.edit') ? 'active' : '' }}">
						Security
					</a>
				</li>
				<li>
					<a href="{{ route('profile.account.edit') }}" class="{{ request()->routeIs('profile.account.edit') ? 'active' : '' }}">
						Account
					</a>
				</li>
			</ul>
		</div>
	</div>
	
	<div class="navbar-end">
		{{-- MODIFIED: Replaced toggle switch with a DaisyUI swap component for a single icon button --}}
		<label class="swap swap-rotate btn btn-ghost btn-circle mr-2">
			{{-- MODIFIED: Added 'hidden' class to prevent the checkbox from being visible --}}
			<input type="checkbox" id="theme-controller" class="hidden" />
			
			{{-- Sun icon (swap-off) --}}
			<svg class="swap-off fill-current w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7.05a1,1,0,0,0,.7.29,1,1,0,0,0,.71-.29l.71-.71a1,1,0,0,0-1.41-1.41l-.71.71A1,1,0,0,0,5.64,7.05ZM18.36,17A1,1,0,0,0,17,18.36l.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41ZM20,12a1,1,0,0,0-1-1H18a1,1,0,0,0,0,2h1A1,1,0,0,0,20,12ZM17,5.64a1,1,0,0,0,.71-.29l.71-.71a1,1,0,1,0-1.41-1.41l-.71.71A1,1,0,0,0,17,5.64ZM12,15a3,3,0,1,0,0-6A3,3,0,0,0,12,15Zm0,2a5,5,0,1,0,0-10A5,5,0,0,0,12,17Z"/></svg>
			
			{{-- Moon icon (swap-on) --}}
			<svg class="swap-on fill-current w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73A8.15,8.15,0,0,1,9.08,5.49a8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22a10.14,10.14,0,0,0,9.5,14.49A8.14,8.14,0,0,1,12.14,19.69Z"/></svg>
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
