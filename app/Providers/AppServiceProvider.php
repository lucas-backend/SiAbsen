<?php

namespace App\Providers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Policies\AttendanceRecordPolicy;
use App\Policies\AttendanceReportPolicy;
use App\Policies\AttendanceRequestPolicy;
use App\Policies\AttendanceSessionPolicy;
use App\Policies\ClassRoomPolicy;
use App\Policies\TeacherAssignmentPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [
        AttendanceSession::class => AttendanceSessionPolicy::class,
        AttendanceRecord::class => AttendanceRecordPolicy::class,
        AttendanceRequest::class => AttendanceRequestPolicy::class,
        SchoolClass::class => ClassRoomPolicy::class,
        TeacherAssignment::class => TeacherAssignmentPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            if ($user instanceof User && $user->isAdmin()) {
                return true;
            }

            return null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Gate::policy('attendance-report', AttendanceReportPolicy::class);
    }
}
