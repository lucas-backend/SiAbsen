<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AcademicSearchController extends Controller
{
    /**
     * Pencarian akademik bertingkat: jenjang → kelas → siswa aktif.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $classId = $validated['class_id'] ?? null;

        if ($classId !== null) {
            $class = SchoolClass::query()->findOrFail((int) $classId);

            Gate::authorize('view', $class);

            $term = $validated['q'] ?? null;

            $students = $class->activeStudents()
                ->with('studentProfile')
                ->when($term !== null && $term !== '', fn ($query) => $query->where('users.name', 'like', '%'.$term.'%'))
                ->orderBy('users.name')
                ->get()
                ->map(fn (User $student): array => [
                    'id' => (int) $student->getKey(),
                    'name' => $student->name,
                    'student_number' => $student->studentProfile?->student_number,
                    'class_id' => (int) $class->getKey(),
                    'class_name' => $class->name,
                ]);

            return response()->json(['data' => $students]);
        }

        $educationLevelId = $validated['education_level_id'] ?? null;

        $classes = SchoolClass::query()
            ->when($educationLevelId !== null, fn ($query) => $query->where('education_level_id', (int) $educationLevelId))
            ->when(! $user->isAdmin(), fn ($query) => $query->whereHas(
                'teacherAssignments',
                fn ($assignment) => $assignment->where('teacher_id', $user->getKey())->where('is_active', true),
            ))
            ->orderBy('name')
            ->get()
            ->map(fn (SchoolClass $class): array => [
                'id' => (int) $class->getKey(),
                'name' => $class->name,
                'education_level_id' => $class->education_level_id,
            ]);

        return response()->json(['data' => $classes]);
    }
}
