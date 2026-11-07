<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AllowanceType;
use App\Models\Department;
use App\Models\HrList;
use App\Models\JobTitle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The managed lists people are set up with: departments (each with its job titles) and allowance types
 * (generic amounts assigned to each person). Anything someone uses can be renamed but not deleted.
 */
class HrListController extends Controller
{
    /** @var array<string, class-string<HrList>> */
    private const LISTS = [
        'departments' => Department::class,
        'job-titles' => JobTitle::class,
        'allowance-types' => AllowanceType::class,
    ];

    public function index(): JsonResponse
    {
        return response()->json([
            'departments' => Department::with('jobTitles')->orderBy('name')->get()->map->toAdminArray()->values(),
            'allowance_types' => AllowanceType::orderBy('name')->get()->map->toAdminArray()->values(),
        ]);
    }

    public function store(Request $request, string $list): JsonResponse
    {
        $model = self::model($list);
        $data = $this->validated($request, $model);
        $item = $model::create($data);
        ActivityLog::record('hr.list.created', "Added {$this->label($list)} “{$item->name}”", $item);

        return response()->json(['data' => $item->load($model === Department::class ? 'jobTitles' : [])->toAdminArray()], 201);
    }

    public function update(Request $request, string $list, int $id): JsonResponse
    {
        $model = self::model($list);
        $item = $model::findOrFail($id);
        $item->update($this->validated($request, $model, $item));
        ActivityLog::record('hr.list.updated', "Updated {$this->label($list)} “{$item->name}”", $item);

        return response()->json(['data' => $item->load($model === Department::class ? 'jobTitles' : [])->toAdminArray()]);
    }

    public function destroy(string $list, int $id): JsonResponse
    {
        $model = self::model($list);
        $item = $model::findOrFail($id);
        $inUse = $item->employees()->exists() || ($item instanceof Department && $item->jobTitles()->exists());
        abort_if($inUse, 422, "“{$item->name}” is in use. Rename it instead, or remove the people and job titles under it first.");
        $name = $item->name;
        $item->delete();
        ActivityLog::record('hr.list.deleted', "Removed {$this->label($list)} “{$name}”");

        return response()->json(status: 204);
    }

    /** @return class-string<HrList> */
    private static function model(string $list): string
    {
        abort_unless(isset(self::LISTS[$list]), 404);

        return self::LISTS[$list];
    }

    private function label(string $list): string
    {
        return match ($list) {
            'departments' => 'department',
            'job-titles' => 'job title',
            default => 'allowance type',
        };
    }

    /** @param  class-string<HrList>  $model */
    private function validated(Request $request, string $model, ?HrList $item = null): array
    {
        if ($model === JobTitle::class) {
            $departmentId = $request->validate(['department_id' => ['required', 'integer', 'exists:departments,id']])['department_id'];
            $data = $request->validate([
                'name' => ['required', 'string', 'max:120', Rule::unique('job_titles', 'name')->where('department_id', $departmentId)->ignore($item?->id)],
            ], ['name.unique' => 'That job title already exists in this department.']);

            return [...$data, 'department_id' => $departmentId];
        }

        $rules = [
            'name' => ['required', 'string', 'max:120', Rule::unique((new $model)->getTable(), 'name')->ignore($item?->id)],
        ];
        if ($model === AllowanceType::class) {
            $rules['is_active'] = ['sometimes', 'boolean'];
        }

        return $request->validate($rules, ['name.unique' => 'That name is already in the list.']);
    }
}
