<?php

namespace App\Http\Controllers\Api\DeptAdmin;

use App\Http\Controllers\Api\Admin\ProgramController as AdminProgramController;
use App\Http\Requests\DeptAdmin\DeptProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\Support\DepartmentScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Department admin: create, edit and delete the programs of their own department.
 * The rules (unique name inside the department, delete blocked while students or courses exist,
 * audit log) live in the super admin ProgramController and are reused here; this class only adds
 * the department restriction.
 */
class DepartmentProgramController extends DeptAdminController
{
    public function __construct(private readonly AdminProgramController $programs)
    {
    }

    /** POST /api/dept/programs */
    public function store(DeptProgramRequest $request): JsonResponse
    {
        return $this->programs->store($request);
    }

    /** PUT /api/dept/programs/{program} */
    public function update(DeptProgramRequest $request, Program $program): ProgramResource
    {
        return $this->programs->update($request, $program);
    }

    /** DELETE /api/dept/programs/{program} */
    public function destroy(Request $request, Program $program): Response|JsonResponse
    {
        DepartmentScope::authorize($request->user(), $program->department_id);

        return $this->programs->destroy($request, $program);
    }
}
