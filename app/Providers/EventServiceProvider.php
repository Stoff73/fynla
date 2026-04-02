<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\CriticalIllnessPolicy;
use App\Models\DCPension;
use App\Models\DisabilityPolicy;
use App\Models\Estate\Asset as EstateAsset;
use App\Models\Estate\Liability as EstateLiability;
use App\Models\FamilyMember;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeEvent;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\SicknessIllnessPolicy;
use App\Models\User;
use App\Observers\DCPensionRiskObserver;
use App\Observers\FamilyMemberRiskObserver;
use App\Observers\InvestmentAccountGoalObserver;
use App\Observers\InvestmentAccountRiskObserver;
use App\Observers\LifeEventMonteCarloObserver;
use App\Observers\LifeEventRiskObserver;
use App\Observers\NetWorthCacheObserver;
use App\Observers\PropertyRiskObserver;
use App\Observers\RecommendationCacheObserver;
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
        FamilyMember::class => [FamilyMemberRiskObserver::class, RecommendationCacheObserver::class],
        SavingsAccount::class => [SavingsAccountRiskObserver::class, SavingsAccountGoalObserver::class, NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        InvestmentAccount::class => [InvestmentAccountRiskObserver::class, InvestmentAccountGoalObserver::class, NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        DCPension::class => [DCPensionRiskObserver::class, NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        Property::class => [PropertyRiskObserver::class, NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        Mortgage::class => [NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        BusinessInterest::class => [NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        Chattel::class => [NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        EstateAsset::class => [NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        EstateLiability::class => [NetWorthCacheObserver::class, RecommendationCacheObserver::class],
        LifeEvent::class => [LifeEventMonteCarloObserver::class, LifeEventRiskObserver::class, RecommendationCacheObserver::class],
        LifeInsurancePolicy::class => [RecommendationCacheObserver::class],
        CriticalIllnessPolicy::class => [RecommendationCacheObserver::class],
        IncomeProtectionPolicy::class => [RecommendationCacheObserver::class],
        DisabilityPolicy::class => [RecommendationCacheObserver::class],
        SicknessIllnessPolicy::class => [RecommendationCacheObserver::class],
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
