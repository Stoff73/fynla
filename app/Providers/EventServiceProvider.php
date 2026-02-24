<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\Investment\InvestmentAccount;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Observers\DCPensionRiskObserver;
use App\Observers\FamilyMemberRiskObserver;
use App\Observers\InvestmentAccountGoalObserver;
use App\Observers\InvestmentAccountRiskObserver;
use App\Observers\PropertyRiskObserver;
use App\Observers\SavingsAccountGoalObserver;
use App\Observers\SavingsAccountRiskObserver;
use App\Observers\UserRiskObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * The model observers for your application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $observers = [
        User::class => [UserRiskObserver::class],
        FamilyMember::class => [FamilyMemberRiskObserver::class],
        SavingsAccount::class => [SavingsAccountRiskObserver::class, SavingsAccountGoalObserver::class],
        InvestmentAccount::class => [InvestmentAccountRiskObserver::class, InvestmentAccountGoalObserver::class],
        DCPension::class => [DCPensionRiskObserver::class],
        Property::class => [PropertyRiskObserver::class],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
