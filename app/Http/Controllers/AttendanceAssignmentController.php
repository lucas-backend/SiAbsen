<?php

namespace App\Http\Controllers;

use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceAssignmentController extends Controller
{
    /**
     * Daftar penugasan mengajar aktif milik guru (atau semua untuk admin).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $assignments = TeacherAssignment::query()
            ->active()
            ->with(['schoolClass', 'subject'])
            ->when(! $user->isAdmin(), fn ($query) => $query->where('teacher_id', $user->getKey()))
            ->orderBy('class_id')
            ->get()
            ->map(fn (TeacherAssignment $assignment): array => [
                'id' => (int) $assignment->getKey(),
                'class_id' => $assignment->class_id,
                'class_name' => $assignment->schoolClass?->name,
                'subject_id' => $assignment->subject_id,
                'subject_name' => $assignment->subject?->name,
            ]);

        return response()->json(['data' => $assignments]);
    }
}
