<?php

namespace App\Policies;

use App\Models\AttendanceRequest;
use App\Models\User;

class AttendanceRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->isAdmin() || $user->isTeacher()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AttendanceRequest $attendanceRequest): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return $this->ownsSession($user, $attendanceRequest);
        }

        if ($user->isStudent()) {
            return $attendanceRequest->student_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isStudent();
    }

    /**
     * Determine whether the user can review (approve/reject) the model.
     *
     * Students can never review, so a student cannot approve their own
     * request. Only the owning teacher or an admin may review.
     */
    public function review(User $user, AttendanceRequest $attendanceRequest): bool
    {
        if ($user->id === $attendanceRequest->student_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return $this->ownsSession($user, $attendanceRequest);
        }

        return false;
    }

    /**
     * Determine whether the user owns the session the request belongs to.
     */
    private function ownsSession(User $user, AttendanceRequest $attendanceRequest): bool
    {
        $session = $attendanceRequest->session;

        if (! $session) {
            return false;
        }

        return $session->assignment?->teacher_id === $user->id
            || $session->created_by === $user->id;
    }
}
