<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Mortgage\MortgageCreated;
use App\Events\Mortgage\MortgageDeleted;
use App\Events\Mortgage\MortgageRestored;
use App\Events\Mortgage\MortgageUpdated;
use App\Events\Property\PropertyCreated;
use App\Events\Property\PropertyDeleted;
use App\Events\Property\PropertyRestored;
use App\Events\Property\PropertyUpdated;
use App\Listeners\Mortgage\RecalculatePropertyOutstandingMortgage;
use App\Listeners\Property\SyncOwnerRentalIncome;
use App\Models\BusinessInterest;
use App\Models\CashAccount;
use App\Models\Chattel;
use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\DisabilityPolicy;
use App\Models\Estate\Asset as EstateAsset;
use App\Models\Estate\Gift;
use App\Models\Estate\Liability as EstateLiability;
use App\Models\Estate\Trust;
use App\Models\ExpenditureProfile;
use App\Models\FamilyMember;
use App\Models\Goal;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeEvent;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\ProtectionProfile;
use App\Models\RetirementProfile;
use App\Models\SavingsAccount;
use App\Models\SicknessIllnessPolicy;
use App\Models\StatePension;
use App\Models\User;
use App\Observers\BusinessInterestObserver;
use App\Observers\DCPensionRiskObserver;
use App\Observers\FamilyMemberRiskObserver;
use App\Observers\InvestmentAccountGoalObserver;
use App\Observers\InvestmentAccountRiskObserver;
use App\Observers\LifeEventMonteCarloObserver;
use App\Observers\LifeEventRiskObserver;
use App\Observers\PropertyRiskObserver;
use App\Observers\SavingsAccountGoalObserver;
use App\Observers\SavingsAccountRiskObserver;
use App\Observers\TrustObserver;
use App\Observers\UserDataCacheObserver;
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
        MortgageCreated::class => [
            RecalculatePropertyOutstandingMortgage::class,
        ],
        MortgageUpdated::class => [
            RecalculatePropertyOutstandingMortgage::class,
        ],
        MortgageDeleted::class => [
            RecalculatePropertyOutstandingMortgage::class,
        ],
        MortgageRestored::class => [
            RecalculatePropertyOutstandingMortgage::class,
        ],
        // Property -> User: keep users.annual_rental_income in step with the
        // records it is derived from, for EVERY user each record reaches (W-0173).
        PropertyCreated::class => [
            SyncOwnerRentalIncome::class,
        ],
        PropertyUpdated::class => [
            SyncOwnerRentalIncome::class,
        ],
        PropertyDeleted::class => [
            SyncOwnerRentalIncome::class,
        ],
        PropertyRestored::class => [
            SyncOwnerRentalIncome::class,
        ],
    ];

    /**
     * The model observers for your application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $observers = [
        User::class => [UserRiskObserver::class],
        FamilyMember::class => [FamilyMemberRiskObserver::class, UserDataCacheObserver::class],
        Goal::class => [UserDataCacheObserver::class],
        SavingsAccount::class => [SavingsAccountRiskObserver::class, SavingsAccountGoalObserver::class, UserDataCacheObserver::class],
        InvestmentAccount::class => [InvestmentAccountRiskObserver::class, InvestmentAccountGoalObserver::class, UserDataCacheObserver::class],
        Holding::class => [UserDataCacheObserver::class],
        DCPension::class => [DCPensionRiskObserver::class, UserDataCacheObserver::class],
        DBPension::class => [UserDataCacheObserver::class],
        StatePension::class => [UserDataCacheObserver::class],
        RetirementProfile::class => [UserDataCacheObserver::class],
        Property::class => [PropertyRiskObserver::class, UserDataCacheObserver::class],
        Mortgage::class => [UserDataCacheObserver::class],
        BusinessInterest::class => [BusinessInterestObserver::class, UserDataCacheObserver::class],
        Chattel::class => [UserDataCacheObserver::class],
        CashAccount::class => [UserDataCacheObserver::class],
        EstateAsset::class => [UserDataCacheObserver::class],
        EstateLiability::class => [UserDataCacheObserver::class],
        Gift::class => [UserDataCacheObserver::class],
        LifeEvent::class => [LifeEventMonteCarloObserver::class, LifeEventRiskObserver::class, UserDataCacheObserver::class],
        LifeInsurancePolicy::class => [UserDataCacheObserver::class],
        CriticalIllnessPolicy::class => [UserDataCacheObserver::class],
        IncomeProtectionPolicy::class => [UserDataCacheObserver::class],
        DisabilityPolicy::class => [UserDataCacheObserver::class],
        SicknessIllnessPolicy::class => [UserDataCacheObserver::class],
        ProtectionProfile::class => [UserDataCacheObserver::class],
        ExpenditureProfile::class => [UserDataCacheObserver::class],
        Trust::class => [TrustObserver::class, UserDataCacheObserver::class],
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
