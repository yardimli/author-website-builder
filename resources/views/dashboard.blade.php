@extends('layouts.app')

@section('content')
<div class="py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        @if (session('success'))
            <div role="alert" class="alert alert-success"><span>{{ session('success') }}</span></div>
        @endif
        @if (session('error'))
            <div role="alert" class="alert alert-error"><span>{{ session('error') }}</span></div>
        @endif

        <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-primary">Your author studio</p>
                <h1 class="mt-2 text-3xl font-bold sm:text-4xl">Websites that begin with your books.</h1>
                <p class="mt-2 max-w-2xl text-base-content/70">Explore the three editable examples, then build a site around your own voice, covers, and readers.</p>
            </div>
            @if($prerequisitesMet)
                <a href="{{ route('websites.create') }}" class="btn btn-primary">Create New Website</a>
            @endif
        </header>

        @if(!$hasUserWebsites)
            <section class="rounded-2xl border border-primary/20 bg-primary/5 p-6 sm:p-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="badge badge-primary badge-outline mb-3">Start here</div>
                        <h2 class="text-2xl font-bold">Turn one of these ideas into your own author site.</h2>
                        <p class="mt-2 text-base-content/75">The examples below are ready to preview, edit, and download. To create your own, complete your profile and add at least one book; the builder will use those details for the first draft.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if(!$profileComplete)
                            <a href="{{ route('profile.edit') }}" class="btn btn-outline">Complete Profile</a>
                        @endif
                        @if(!$hasBooks)
                            <a href="{{ route('profile.books.edit') }}" class="btn btn-outline">Add a Book</a>
                        @endif
                        @if($prerequisitesMet)
                            <a href="{{ route('websites.create') }}" class="btn btn-primary">Build My Site</a>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        @if($hasUserWebsites)
            <section>
                <div class="mb-4 flex items-center justify-between">
                    <div><p class="text-sm font-semibold text-primary">Your work</p><h2 class="text-2xl font-bold">Your websites</h2></div>
                </div>
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($userWebsites as $website)
                        <article class="card border border-base-300 bg-base-100 shadow-sm">
                            <div class="card-body">
                                <h3 class="card-title">{{ $website->name }}</h3>
                                <p class="text-sm text-base-content/60">Created {{ $website->created_at->toFormattedDateString() }}</p>
                                <div class="card-actions mt-4 justify-end">
                                    <a href="{{ route('websites.download', $website) }}" class="btn btn-ghost btn-sm">Download ZIP</a>
                                    <button class="btn btn-ghost btn-sm" onclick="document.getElementById('slug_modal_{{ $website->id }}').showModal()">Settings</button>
                                    <a href="{{ route('websites.show', $website) }}" class="btn btn-primary btn-sm">Open Editor</a>
                                </div>
                            </div>
                        </article>

                        <dialog id="slug_modal_{{ $website->id }}" class="modal">
                            <div class="modal-box">
                                <h3 class="text-lg font-bold">Website settings</h3>
                                <p class="py-2 text-sm text-base-content/70">Change the public URL for �{{ $website->name }}�.</p>
                                <form method="POST" action="{{ route('websites.slug.update', $website) }}" class="slug-update-form space-y-4 pt-4" data-website-id="{{ $website->id }}">
                                    @csrf
                                    @method('PATCH')
                                    <label class="form-control">
                                        <span class="label-text mb-2">Website URL</span>
                                        <div class="join w-full">
                                            <span class="join-item btn btn-disabled">{{ url('/website') }}/</span>
                                            <input type="text" name="slug" class="slug-input input join-item input-bordered w-full" required value="{{ old('slug', $website->slug) }}">
                                        </div>
                                        <span class="slug-feedback mt-1 min-h-5 text-sm"></span>
                                    </label>
                                    <div class="modal-action"><button type="button" class="btn" onclick="this.closest('dialog').close()">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
                                </form>
                            </div>
                            <form method="dialog" class="modal-backdrop"><button>close</button></form>
                        </dialog>
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div><p class="text-sm font-semibold text-primary">Editable examples</p><h2 class="text-2xl font-bold">Three genres, three starting points</h2></div>
                <p class="text-sm text-base-content/60">Open any demo in the same AI editor used for your own sites.</p>
            </div>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                @foreach($demoWebsites as $website)
                    @php
                        $genre = ucfirst($website->demo_key);
                        $description = [
                            'romance' => 'Soft editorial type, warm color, and a tender book-first layout.',
                            'suspense' => 'High contrast, sharp pacing, and a cinematic call to action.',
                            'fantasy' => 'Deep jewel tones, luminous details, and immersive worldbuilding.',
                        ][$website->demo_key] ?? 'An editable author website example.';
                    @endphp
                    <article class="group overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
                        <div class="relative aspect-[5/3] overflow-hidden bg-base-200">
                            <img src="{{ asset('images/demo-sites/'.$website->demo_key.'/site-thumbnail.png') }}" alt="Zoomed-out desktop preview of the {{ $genre }} author website" class="h-full w-full object-cover object-top transition duration-500 group-hover:scale-[1.02]" loading="lazy">
                            <div class="absolute left-4 top-4 badge badge-primary">{{ $genre }} demo</div>
                        </div>
                        <div class="p-5">
                            <h3 class="text-xl font-bold">{{ $website->name }}</h3>
                            <p class="mt-2 min-h-12 text-sm text-base-content/65">{{ $description }}</p>
                            <div class="mt-5 flex flex-wrap gap-2">
                                <a href="{{ route('website.preview.serve', $website) }}" target="_blank" class="btn btn-ghost btn-sm">Full Preview</a>
                                <a href="{{ route('websites.download', $website) }}" class="btn btn-ghost btn-sm">Download ZIP</a>
                                <a href="{{ route('websites.show', $website) }}" class="btn btn-primary btn-sm">Try in Editor</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div role="alert" class="alert alert-info"><span>This service is in beta. Review generated copy, links, and mobile layouts before sharing your website.</span></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let debounceTimer;
    document.querySelectorAll('.slug-update-form').forEach(form => {
        const input = form.querySelector('.slug-input');
        const feedback = form.querySelector('.slug-feedback');
        const submit = form.querySelector('button[type="submit"]');
        input.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                const slug = input.value.trim();
                if (!/^[a-zA-Z0-9-_]{3,}$/.test(slug)) {
                    feedback.innerHTML = '<span class="text-error">Use at least 3 letters, numbers, dashes, or underscores.</span>';
                    submit.disabled = true;
                    return;
                }
                feedback.textContent = 'Checking availability...';
                submit.disabled = true;
                try {
                    const response = await fetch('{{ route('websites.slug.check') }}', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
                        body: JSON.stringify({slug, ignore_id: form.dataset.websiteId})
                    });
                    const data = await response.json();
                    feedback.innerHTML = data.available ? '<span class="text-success">Available</span>' : '<span class="text-error">This URL is already taken.</span>';
                    submit.disabled = !data.available;
                } catch (error) {
                    feedback.innerHTML = '<span class="text-error">Could not verify this URL.</span>';
                }
            }, 400);
        });
    });
});
</script>
@endpush
