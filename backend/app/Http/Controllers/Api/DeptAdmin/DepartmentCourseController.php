<?php

namespace App\Http\Controllers\Api\DeptAdmin;

use App\Http\Controllers\Api\Admin\CourseController as AdminCourseController;
use App\Http\Requests\DeptAdmin\DeptCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Support\DepartmentScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Department admin: manage the courses of their own department and assign lecturers.
 * The rules (unique code, locked fields once records exist, one lecturer per session, audit log)
 * live in the super admin CourseController and are reused here; this class only adds the
 * department restriction.
 */
class DepartmentCourseController extends DeptAdminController
{
    public function __construct(private readonly AdminCourseController $courses)
    {
    }

    /** POST /api/dept/courses */
    public function store(DeptCourseRequest $request): JsonResponse
    {
        return $this->courses->store($request);
    }

    /** PUT /api/dept/courses/{course} */
    public function update(DeptCourseRequest $request, Course $course): CourseResource
    {
        return $this->courses->update($request, $course);
    }

    /** DELETE /api/dept/courses/{course} */
    public function destroy(Request $request, Course $course): Response
    {
        DepartmentScope::authorize($request->user(), $course->department_id);

        return $this->courses->destroy($request, $course);
    }

    /** POST /api/dept/courses/{course}/assign  body: { lecturerId: id | null } */
    public function assign(Request $request, Course $course): CourseResource
    {
        $user = $request->user();
        DepartmentScope::authorize($user, $course->department_id);

        $data = $request->validate(['lecturerId' => ['present', 'nullable', 'integer', 'exists:lecturers,id']]);
        $lecturerId = $data['lecturerId'] !== null ? (int) $data['lecturerId'] : null;

        if ($lecturerId !== null) {
            // The lecturer must belong to the same department as the course.
            $inDepartment = \App\Models\Lecturer::whereKey($lecturerId)->where('department_id', $course->department_id)->exists();
            if (! $inDepartment) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lecturerId' => 'The lecturer must belong to the same department as the course.',
                ]);
            }
        }

        $this->courses->assign($course, $lecturerId, $user);

        return new CourseResource($course->fresh(['department', 'currentAssignment.lecturer.user']));
    }
}
