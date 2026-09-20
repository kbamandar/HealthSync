<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\ExtractRecordFileOcr;
use App\Models\AuditEvent;
use App\Models\FamilyMember;
use App\Models\HealthRecord;
use App\Models\RecordFile;
use App\Services\Family\FamilyGroupProvisioner;
use App\Services\Records\RecordStorageService;
use App\Services\Records\VirusScanService;
use App\Services\Reminders\VaccinationReminderSuggester;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HealthRecordController extends Controller
{
    private const CATEGORIES = [
        'lab_report', 'radiology', 'prescription', 'discharge_summary', 'vaccination',
        'chronic_condition', 'allergy', 'vital_signs', 'dental', 'eye', 'insurance',
        'fitness_lifestyle', 'other',
    ];

    public function __construct(
        private readonly FamilyGroupProvisioner $familyGroups,
        private readonly RecordStorageService $storage,
        private readonly VirusScanService $virusScan,
        private readonly VaccinationReminderSuggester $vaccinationReminders,
    ) {}

    public function index(Request $request)
    {
        $group = $this->familyGroups->ensureForUser($request->user());

        $query = HealthRecord::query()
            ->where('family_group_id', $group->id)
            ->active();

        if ($request->filled('member_id')) {
            $query->where('member_id', $request->string('member_id'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('from')) {
            $query->whereDate('record_date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('record_date', '<=', $request->date('to'));
        }

        if ($request->filled('q')) {
            $query->whereRaw(
                "to_tsvector('english', coalesce(title,'') || ' ' || coalesce(notes,'')) @@ plainto_tsquery('english', ?)",
                [$request->string('q')],
            );
        }

        $perPage = min((int) $request->integer('per_page', 20), 100);
        $records = $query->orderByDesc('record_date')->orderByDesc('created_at')->paginate($perPage);

        return ApiResponse::success(
            collect($records->items())->map(fn (HealthRecord $r) => $this->serialize($r))->all(),
            [
                'page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'uuid'],
            'category' => ['required', 'string', 'in:'.implode(',', self::CATEGORIES)],
            'title' => ['nullable', 'string', 'max:500'],
            'record_date' => ['nullable', 'date'],
            'doctor_name' => ['nullable', 'string', 'max:255'],
            'hospital_clinic' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'custom_tags' => ['sometimes', 'array'],
            'custom_tags.*' => ['string', 'max:50'],
        ]);

        $group = $this->familyGroups->ensureForUser($request->user());

        $member = FamilyMember::query()
            ->where('id', $data['member_id'])
            ->where('family_group_id', $group->id)
            ->active()
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'member_id' => 'This family member was not found in your family group.',
            ]);
        }

        $record = HealthRecord::create([
            'family_group_id' => $group->id,
            'member_id' => $member->id,
            'uploaded_by_id' => $request->user()->id,
            'category' => $data['category'],
            'title' => $data['title'] ?? null,
            'record_date' => $data['record_date'] ?? null,
            'doctor_name' => $data['doctor_name'] ?? null,
            'hospital_clinic' => $data['hospital_clinic'] ?? null,
            'notes' => $data['notes'] ?? null,
            'custom_tags' => $data['custom_tags'] ?? [],
            'is_favourite' => false,
            'is_deleted' => false,
        ]);

        AuditEvent::record('record.upload', $request->user()->id, $request, ['health_record_id' => $record->id]);

        $this->vaccinationReminders->suggestFor($record);

        return ApiResponse::success($this->serialize($record));
    }

    public function show(Request $request, string $id)
    {
        $record = $this->findOwnedRecord($request, $id);

        if (! $record) {
            return ApiResponse::error('NOT_FOUND', 'Health record not found.', status: 404);
        }

        AuditEvent::record('record.view', $request->user()->id, $request, ['health_record_id' => $record->id]);

        return ApiResponse::success($this->serialize($record, withFiles: true));
    }

    public function update(Request $request, string $id)
    {
        $record = $this->findOwnedRecord($request, $id);

        if (! $record) {
            return ApiResponse::error('NOT_FOUND', 'Health record not found.', status: 404);
        }

        $data = $request->validate([
            'category' => ['sometimes', 'string', 'in:'.implode(',', self::CATEGORIES)],
            'title' => ['sometimes', 'nullable', 'string', 'max:500'],
            'record_date' => ['sometimes', 'nullable', 'date'],
            'doctor_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hospital_clinic' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'is_favourite' => ['sometimes', 'boolean'],
            'custom_tags' => ['sometimes', 'array'],
            'custom_tags.*' => ['string', 'max:50'],
        ]);

        $record->update($data);

        AuditEvent::record('record.update', $request->user()->id, $request, ['health_record_id' => $record->id]);

        return ApiResponse::success($this->serialize($record, withFiles: true));
    }

    public function destroy(Request $request, string $id)
    {
        $record = $this->findOwnedRecord($request, $id);

        if (! $record) {
            return ApiResponse::error('NOT_FOUND', 'Health record not found.', status: 404);
        }

        $record->update(['is_deleted' => true, 'deleted_at' => now()]);

        AuditEvent::record('record.delete', $request->user()->id, $request, ['health_record_id' => $record->id]);

        return ApiResponse::success();
    }

    public function uploadUrl(Request $request, string $id)
    {
        $record = $this->findOwnedRecord($request, $id);

        if (! $record) {
            return ApiResponse::error('NOT_FOUND', 'Health record not found.', status: 404);
        }

        $data = $request->validate([
            'mime_type' => ['required', 'string'],
        ]);

        if (! $this->storage->isAllowedMimeType($data['mime_type'])) {
            return ApiResponse::error('UNSUPPORTED_FILE_TYPE', 'Only PDF, JPG, and PNG files are supported.', status: 422);
        }

        $upload = $this->storage->createUploadUrl($data['mime_type']);

        return ApiResponse::success([
            'upload_url' => $upload['url'],
            's3_key' => $upload['key'],
            'expires_in' => $upload['expires_in'],
        ]);
    }

    public function registerFile(Request $request, string $id)
    {
        $record = $this->findOwnedRecord($request, $id);

        if (! $record) {
            return ApiResponse::error('NOT_FOUND', 'Health record not found.', status: 404);
        }

        $data = $request->validate([
            's3_key' => ['required', 'string'],
            'mime_type' => ['required', 'string'],
        ]);

        if (! $this->storage->isAllowedMimeType($data['mime_type'])) {
            return ApiResponse::error('UNSUPPORTED_FILE_TYPE', 'Only PDF, JPG, and PNG files are supported.', status: 422);
        }

        if (! $this->storage->exists($data['s3_key'])) {
            return ApiResponse::error('FILE_NOT_UPLOADED', 'No file was found at the given key. Upload it first.', status: 422);
        }

        if (! $this->virusScan->scan($data['s3_key'])) {
            return ApiResponse::error('FILE_REJECTED', 'This file failed a security scan and was not saved.', status: 422);
        }

        $file = RecordFile::create([
            'record_id' => $record->id,
            'file_type' => $this->storage->extensionForMimeType($data['mime_type']),
            's3_key' => $data['s3_key'],
            'file_size_bytes' => $this->storage->size($data['s3_key']),
            'mime_type' => $data['mime_type'],
        ]);

        AuditEvent::record('record.upload', $request->user()->id, $request, [
            'health_record_id' => $record->id,
            'record_file_id' => $file->id,
        ]);

        ExtractRecordFileOcr::dispatch($file->id);

        return ApiResponse::success($this->serializeFile($file));
    }

    public function applyOcrData(Request $request, string $id)
    {
        $record = $this->findOwnedRecord($request, $id);

        if (! $record) {
            return ApiResponse::error('NOT_FOUND', 'Health record not found.', status: 404);
        }

        $file = $record->files()->where('ocr_extracted', true)->latest('created_at')->first();

        if (! $file || empty($file->ocr_data['fields'] ?? null)) {
            return ApiResponse::error('OCR_NOT_READY', 'No extracted data is available for this record yet.', status: 422);
        }

        $fields = $file->ocr_data['fields'];
        $updates = [];

        if (! $record->record_date && ! empty($fields['record_date'])) {
            $updates['record_date'] = $fields['record_date'];
        }

        if (! $record->hospital_clinic && ! empty($fields['lab_name'])) {
            $updates['hospital_clinic'] = $fields['lab_name'];
        }

        if (! $record->notes && ! empty($fields['test_values'])) {
            $updates['notes'] = collect($fields['test_values'])
                ->map(fn (array $t) => $t['name'].': '.$t['value'].($t['unit'] ? ' '.$t['unit'] : ''))
                ->implode("\n");
        }

        if ($updates !== []) {
            $record->update($updates);
        }

        return ApiResponse::success($this->serialize($record, withFiles: true));
    }

    public function recycleBin(Request $request)
    {
        $group = $this->familyGroups->ensureForUser($request->user());

        $records = HealthRecord::query()
            ->where('family_group_id', $group->id)
            ->trashed()
            ->orderByDesc('deleted_at')
            ->get();

        return ApiResponse::success($records->map(fn (HealthRecord $r) => $this->serialize($r))->all());
    }

    public function restore(Request $request, string $id)
    {
        $record = $this->findOwnedRecord($request, $id, trashed: true);

        if (! $record) {
            return ApiResponse::error('NOT_FOUND', 'Deleted health record not found.', status: 404);
        }

        $record->update(['is_deleted' => false, 'deleted_at' => null]);

        AuditEvent::record('record.restore', $request->user()->id, $request, ['health_record_id' => $record->id]);

        return ApiResponse::success($this->serialize($record));
    }

    private function findOwnedRecord(Request $request, string $id, bool $trashed = false): ?HealthRecord
    {
        $group = $this->familyGroups->ensureForUser($request->user());

        $query = HealthRecord::query()->where('id', $id)->where('family_group_id', $group->id);

        return $trashed ? $query->trashed()->first() : $query->active()->first();
    }

    private function serialize(HealthRecord $record, bool $withFiles = false): array
    {
        $data = [
            'id' => $record->id,
            'member_id' => $record->member_id,
            'category' => $record->category,
            'title' => $record->title,
            'record_date' => $record->record_date?->toDateString(),
            'doctor_name' => $record->doctor_name,
            'hospital_clinic' => $record->hospital_clinic,
            'notes' => $record->notes,
            'is_favourite' => $record->is_favourite,
            'custom_tags' => $record->custom_tags,
            'deleted_at' => $record->deleted_at?->toIso8601String(),
            'created_at' => $record->created_at->toIso8601String(),
        ];

        if ($withFiles) {
            $data['files'] = $record->files->map(fn (RecordFile $f) => $this->serializeFile($f))->all();
        }

        return $data;
    }

    private function serializeFile(RecordFile $file): array
    {
        return [
            'id' => $file->id,
            'file_type' => $file->file_type,
            'mime_type' => $file->mime_type,
            'file_size_bytes' => $file->file_size_bytes,
            'download_url' => $this->storage->createDownloadUrl($file->s3_key),
            'ocr_extracted' => $file->ocr_extracted,
            'ocr_data' => $file->ocr_data,
            'created_at' => $file->created_at?->toIso8601String(),
        ];
    }
}
