{{--
This partial displays the header and progress for the multi-step setup wizard.
It expects the following variables:
- $step: The current step number (e.g., 1, 2, 3).
--}}
@props(['step' => 1])

<div class="mb-8 p-4 border-b border-base-300">
	<h2 class="text-2xl font-bold text-center">Account Setup Wizard</h2>
	<p class="text-center text-sm text-base-content/70">Let's get your author platform ready.</p>
	
	<ul class="steps w-full mt-6">
		<li class="step {{ $step >= 1 ? 'step-primary' : '' }}">
			<span class="text-xs sm:text-sm">Import Books</span>
		</li>
		<li class="step {{ $step >= 2 ? 'step-primary' : '' }}">
			<span class="text-xs sm:text-sm">Complete Profile</span>
		</li>
		<li class="step {{ $step >= 3 ? 'step-primary' : '' }}">
			<span class="text-xs sm:text-sm">Create Website</span>
		</li>
	</ul>
</div>
