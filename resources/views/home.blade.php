<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Build a polished author website from your books, bio, and a conversation with AI.">
    <title>{{ config('app.name', 'Author Website Builder') }} | Your books deserve a home</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|playfair-display:600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root{color-scheme:light}.landing{font-family:'DM Sans',sans-serif}.display{font-family:'Playfair Display',serif}.hero-glow{background:radial-gradient(circle at 72% 35%,rgba(143,49,85,.2),transparent 35%),radial-gradient(circle at 15% 10%,rgba(199,155,59,.16),transparent 28%)}.grain{background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.035'/%3E%3C/svg%3E")}.shot{box-shadow:0 30px 80px rgba(42,31,31,.2)}
    </style>
</head>
<body class="landing bg-[#f7f5f1] text-[#282420] antialiased">
<div class="grain min-h-screen overflow-hidden">
    <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-6 lg:px-8">
        <a href="{{ route('home') }}" class="text-lg font-bold tracking-tight">{{ config('app.name', 'Author Website Builder') }}</a>
        <div class="flex items-center gap-3">
            <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Log in</a>
            <a href="{{ route('register') }}" class="btn btn-neutral btn-sm">Start building</a>
        </div>
    </nav>

    <main>
        <section class="hero-glow mx-auto max-w-[1440px] px-5 pb-24 pt-16 text-center sm:pt-24 lg:px-8">
            <p class="mx-auto mb-5 w-fit rounded-full border border-[#8f3155]/20 bg-white/60 px-4 py-2 text-xs font-bold uppercase tracking-[.2em] text-[#8f3155]">AI website studio for authors</p>
            <h1 class="display mx-auto max-w-5xl text-5xl font-bold leading-[.98] tracking-[-.035em] sm:text-7xl lg:text-8xl">Your books have a world.<br><span class="text-[#8f3155]">Give it a front door.</span></h1>
            <p class="mx-auto mt-7 max-w-2xl text-lg leading-8 text-[#655d57]">Bring your bio, covers, and book details. Describe the mood you want. The builder creates the site, then keeps refining it with you.</p>
            <div class="mt-9 flex flex-wrap justify-center gap-3"><a href="{{ route('register') }}" class="btn btn-neutral btn-lg">Create your author site</a><a href="#inside" class="btn btn-ghost btn-lg">See how it works</a></div>
            <div class="relative mx-auto mt-20 max-w-6xl pb-20">
                <img src="{{ asset('images/landing/editor-light.svg') }}" alt="Light mode author website editor with an AI conversation and romance website preview" class="shot relative z-10 w-[88%] rounded-2xl border border-black/10 bg-white">
                <img src="{{ asset('images/landing/editor-dark.svg') }}" alt="Dark mode author website editor with an AI conversation and suspense website preview" class="shot absolute bottom-0 right-0 z-20 w-[58%] rounded-2xl border border-white/10">
            </div>
        </section>

        <section id="inside" class="bg-[#17191c] px-5 py-24 text-white lg:px-8 lg:py-32">
            <div class="mx-auto max-w-7xl">
                <div class="grid gap-10 lg:grid-cols-[.8fr_1.2fr] lg:items-end">
                    <div><p class="text-xs font-bold uppercase tracking-[.22em] text-[#e87967]">A simpler creative loop</p><h2 class="display mt-4 text-4xl leading-tight sm:text-6xl">Say what you mean.<br>See what changes.</h2></div>
                    <p class="max-w-xl text-lg leading-8 text-white/60 lg:justify-self-end">No blank canvas and no template maze. Ask for a stronger headline, a section for reviews, a softer palette, or a mobile navigation fix. Preview and code stay beside the conversation.</p>
                </div>
                <div class="mt-14 grid gap-8 lg:grid-cols-2">
                    <figure><img src="{{ asset('images/landing/editor-light.svg') }}" alt="Light mode AI editor screenshot" class="w-full rounded-2xl border border-white/10"><figcaption class="mt-4 text-sm text-white/55"><strong class="text-white">Light mode:</strong> “Make the hero more romantic using colors from my cover.”</figcaption></figure>
                    <figure class="lg:mt-20"><img src="{{ asset('images/landing/editor-dark.svg') }}" alt="Dark mode AI editor screenshot" class="w-full rounded-2xl border border-white/10"><figcaption class="mt-4 text-sm text-white/55"><strong class="text-white">Dark mode:</strong> “Add reviews and improve the mobile menu contrast.”</figcaption></figure>
                </div>
            </div>
        </section>

        <section class="px-5 py-24 lg:px-8 lg:py-32">
            <div class="mx-auto max-w-7xl">
                <div class="max-w-3xl"><p class="text-xs font-bold uppercase tracking-[.22em] text-[#8f3155]">Built for the genre</p><h2 class="display mt-4 text-4xl leading-tight sm:text-6xl">Not one author template.<br>Three very different rooms.</h2><p class="mt-6 text-lg leading-8 text-[#6b635d]">Every account opens with romance, suspense, and fantasy examples. Edit them, inspect the code, download the files, or use them as a visual starting point.</p></div>
                <div class="mt-14 grid gap-7 lg:grid-cols-12">
                    <figure class="lg:col-span-8"><div class="overflow-hidden rounded-2xl border border-black/10 bg-white p-2 shadow-xl"><img src="{{ asset('images/landing/romance-desktop.svg') }}" alt="Full desktop romance author website" class="w-full rounded-xl"></div><figcaption class="mt-4 flex justify-between text-sm"><span class="font-semibold">Romance, full desktop</span><span class="text-[#766d66]">Warm editorial direction</span></figcaption></figure>
                    <figure class="mx-auto w-full max-w-[390px] lg:col-span-4"><div class="rounded-[2.5rem] border-[10px] border-[#282b30] bg-[#282b30] shadow-2xl"><img src="{{ asset('images/landing/suspense-mobile.svg') }}" alt="Full mobile suspense author website" class="w-full rounded-[1.8rem]"></div><figcaption class="mt-4 flex justify-between text-sm"><span class="font-semibold">Suspense, full mobile</span><span class="text-[#766d66]">Fast and cinematic</span></figcaption></figure>
                    <figure class="lg:col-span-10 lg:col-start-2"><div class="overflow-hidden rounded-2xl border border-black/10 bg-white p-2 shadow-xl"><img src="{{ asset('images/landing/fantasy-detail.svg') }}" alt="Fantasy author website worldbuilding section" class="w-full rounded-xl"></div><figcaption class="mt-4 flex justify-between text-sm"><span class="font-semibold">Fantasy, section detail</span><span class="text-[#766d66]">Worldbuilding beyond the cover</span></figcaption></figure>
                </div>
            </div>
        </section>

        <section class="px-5 pb-24 lg:px-8 lg:pb-32">
            <div class="mx-auto grid max-w-7xl overflow-hidden rounded-3xl bg-[#8f3155] text-white lg:grid-cols-2">
                <div class="p-8 sm:p-14"><p class="text-xs font-bold uppercase tracking-[.22em] text-white/65">Your site stays yours</p><h2 class="display mt-4 text-4xl sm:text-5xl">Preview it. Edit the code. Take it with you.</h2><p class="mt-6 max-w-xl text-lg leading-8 text-white/75">Download a ZIP containing the latest HTML, CSS, JavaScript, and image files. Host it elsewhere, hand it to a developer, or keep a portable copy.</p></div>
                <div class="flex items-center justify-center bg-[#6f213f] p-10"><div class="w-full max-w-sm rounded-2xl border border-white/15 bg-white/10 p-5 backdrop-blur"><div class="flex items-center justify-between border-b border-white/15 pb-4"><span class="font-semibold">elena-marlowe.zip</span><span class="rounded-full bg-white/15 px-3 py-1 text-xs">Ready</span></div><div class="space-y-3 pt-4 font-mono text-sm text-white/75"><p>index.html</p><p>css/style.css</p><p>js/script.js</p><p>assets/cover.svg</p><p>storage/author-photo.jpg</p></div></div></div>
            </div>
        </section>

        <section class="border-t border-black/10 px-5 py-24 text-center lg:px-8"><p class="text-xs font-bold uppercase tracking-[.22em] text-[#8f3155]">The next chapter needs a URL</p><h2 class="display mx-auto mt-4 max-w-4xl text-5xl leading-tight sm:text-7xl">Build the place readers find after the final page.</h2><a href="{{ route('register') }}" class="btn btn-neutral btn-lg mt-9">Start with three examples</a></section>
    </main>

    <footer class="mx-auto flex max-w-7xl flex-col gap-3 border-t border-black/10 px-5 py-8 text-sm text-[#766d66] sm:flex-row sm:items-center sm:justify-between lg:px-8"><span>{{ config('app.name', 'Author Website Builder') }}</span><span>Author sites shaped in conversation.</span></footer>
</div>
</body>
</html>
