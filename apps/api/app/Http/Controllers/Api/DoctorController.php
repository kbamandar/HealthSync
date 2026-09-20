<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Models\HealthRecord;
use App\Services\Family\FamilyGroupProvisioner;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function __construct(private readonly FamilyGroupProvisioner $familyGroups) {}

    public function index(Request $request)
    {
        $group = $this->familyGroups->ensureForUser($request->user());

        $query = Doctor::query()->where('family_group_id', $group->id);

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', $term)
                    ->orWhere('speciality', 'ilike', $term)
                    ->orWhere('hospital_clinic', 'ilike', $term);
            });
        }

        $doctors = $query->orderBy('name')->get();

        return ApiResponse::success($doctors->map(fn (Doctor $d) => $this->serialize($d))->all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'speciality' => ['nullable', 'string', 'max:255'],
            'hospital_clinic' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'location' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $group = $this->familyGroups->ensureForUser($request->user());

        $doctor = Doctor::create([
            'family_group_id' => $group->id,
            ...$data,
        ]);

        return ApiResponse::success($this->serialize($doctor));
    }

    public function update(Request $request, string $id)
    {
        $doctor = $this->findOwnedDoctor($request, $id);

        if (! $doctor) {
            return ApiResponse::error('NOT_FOUND', 'Doctor not found.', status: 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'speciality' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hospital_clinic' => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'location' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $doctor->update($data);

        return ApiResponse::success($this->serialize($doctor));
    }

    public function destroy(Request $request, string $id)
    {
        $doctor = $this->findOwnedDoctor($request, $id);

        if (! $doctor) {
            return ApiResponse::error('NOT_FOUND', 'Doctor not found.', status: 404);
        }

        $doctor->delete();

        return ApiResponse::success();
    }

    /**
     * Not in the original product schema doc — health_records has no FK to
     * doctors, so "visit history" is matched by name/clinic text against
     * the family group's own records rather than a join.
     */
    public function visits(Request $request, string $id)
    {
        $doctor = $this->findOwnedDoctor($request, $id);

        if (! $doctor) {
            return ApiResponse::error('NOT_FOUND', 'Doctor not found.', status: 404);
        }

        $records = HealthRecord::query()
            ->where('family_group_id', $doctor->family_group_id)
            ->active()
            ->where(function ($query) use ($doctor) {
                $query->where('doctor_name', 'ilike', $doctor->name);

                if ($doctor->hospital_clinic) {
                    $query->orWhere('hospital_clinic', 'ilike', $doctor->hospital_clinic);
                }
            })
            ->orderByDesc('record_date')
            ->get();

        return ApiResponse::success($records->map(fn (HealthRecord $r) => [
            'record_id' => $r->id,
            'title' => $r->title,
            'category' => $r->category,
            'record_date' => $r->record_date?->toDateString(),
        ])->all());
    }

    private function findOwnedDoctor(Request $request, string $id): ?Doctor
    {
        $group = $this->familyGroups->ensureForUser($request->user());

        return Doctor::query()->where('id', $id)->where('family_group_id', $group->id)->first();
    }

    private function serialize(Doctor $doctor): array
    {
        return [
            'id' => $doctor->id,
            'name' => $doctor->name,
            'speciality' => $doctor->speciality,
            'hospital_clinic' => $doctor->hospital_clinic,
            'phone' => $doctor->phone,
            'location' => $doctor->location,
            'notes' => $doctor->notes,
            'created_at' => $doctor->created_at->toIso8601String(),
        ];
    }
}
