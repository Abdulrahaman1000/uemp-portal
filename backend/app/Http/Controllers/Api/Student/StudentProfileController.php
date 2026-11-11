<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Resources\StudentResource;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class StudentProfileController extends StudentController
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** GET /api/student/profile */
    public function show(Request $request): array
    {
        return $this->profile($this->student($request));
    }

    /**
     * PUT /api/student/profile
     * Limited edit: only phone, address and date of birth. Matric number, department, program,
     * level and status can never be changed here (other fields in the body are ignored).
     */
    public function update(Request $request): array
    {
        $student = $this->student($request);

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'dateOfBirth' => ['nullable', 'date', 'after:1900-01-01', 'before:today'],
        ], [
            'phone.regex' => 'Enter a valid phone number.',
            'dateOfBirth.before' => 'Date of birth must be in the past.',
        ]);

        $changes = [];
        foreach (['phone' => 'phone', 'address' => 'address', 'dateOfBirth' => 'date_of_birth'] as $key => $column) {
            if (array_key_exists($key, $data)) {
                $changes[$column] = $data[$key];
            }
        }

        $student->fill($changes);
        $changed = array_keys($student->getDirty());
        $student->save();

        if ($changed !== []) {
            $this->audit->record(
                $request->user(),
                'PROFILE_UPDATED',
                $student->matric_no,
                'Updated profile: '.implode(', ', $changed),
                $student,
            );
        }

        return $this->profile($student->refresh()->load('user', 'department.faculty', 'program'));
    }

    /** @return array<string, mixed> */
    private function profile($student): array
    {
        return array_merge((new StudentResource($student))->resolve(), [
            'phone' => $student->phone,
            'address' => $student->address,
            'dateOfBirth' => $student->date_of_birth?->format('Y-m-d'),
        ]);
    }
}
