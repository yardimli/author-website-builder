<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\Book;
use App\Models\Website;
use App\Policies\BookPolicy;
use App\Policies\WebsitePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
	    Website::class => WebsitePolicy::class,
	    Book::class => BookPolicy::class,
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
	    $this->registerPolicies();

	    //
    }
}
