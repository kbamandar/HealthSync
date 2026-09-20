<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\FamilyMember;
use App\Models\HealthRecord;
use App\Models\Reminder;
use App\Services\Family\FamilyGroupProvisioner;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReminderController extends Controller
{
    private const REMINDER_TYPES = ['medication', 'appointment', 'vaccination', 'annual_checkup', 'lab_repeat'];

    private const RECURRENCES = ['daily', 'weekly', 'monthly', 'custom'];

    private const NOTIFY_CHANNELS = ['push', 'sms', 'whatsapp', 'email'];

    public function __construct(private readonly FamilyGroupProvisioner $familyGroups) {}

    public function index(Request $request)
    {
        $group = $this->familyGroups->ensureForUser($request->user());

        $query = Reminder::query()->where('family_group_id', $group->id);

        if ($request->filled('member_id')) {
            $query->where('member_id', $request->string('member_id'));
        }

        if ($request->boolean('upcoming')) {
            $query->active()->where('due_at', '>=', now());
        }

        $reminders = $query->orderBy('due_at')->get();

        return ApiResponse::success($reminders->map(fn (Reminder $r) => $this->serialize($r))->all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'uuid'],
            'reminder_type' => ['required', 'string', 'in:'.implode(',', self::REMINDER_TYPES)],
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'recurrence' => ['nullable', 'string', 'in:'.implode(',', self::RECURRENCES)],
            'notify_via' => ['sometimes', 'array'],
            'notify_via.*' => ['string', 'in:'.implode(',', self::NOTIFY_CHANNELS)],
            'linked_record_id' => ['nullable', 'uuid'],
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

        if (! empty($data['linked_record_id'])) {
            $recordExists = HealthRecord::query()
                ->where('id', $data['linked_record_id'])
                ->where('family_group_id', $group->id)
                ->exists();

            if (! $recordExists) {
                throw ValidationException::withMessages([
                    'linked_record_id' => 'This record was not found in your family group.',
                ]);
            }
        }

        $reminder = Reminder::create([
            'family_group_id' => $group->id,
            'member_id' => $member->id,
            'created_by_id' => $request->user()->id,
            'reminder_type' => $data['reminder_type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_at' => $data['due_at'],
            'recurrence' => $data['recurrence'] ?? null,
            'notify_via' => $data['notify_via'] ?? ['push'],
            'is_active' => true,
            'linked_record_id' => $data['linked_record_id'] ?? null,
        ]);

        return ApiResponse::success($this->serialize($reminder));
    }

    public function update(Request $request, string $id)
    {
        $reminder = $this->findOwnedReminder($request, $id);

        if (! $reminder) {
            return ApiResponse::error('NOT_FOUND', 'Reminder not found.', status: 404);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string'],
            'due_at' => ['sometimes', 'date'],
            'recurrence' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', self::RECURRENCES)],
            'notify_via' => ['sometimes', 'array'],
            'notify_via.*' => ['string', 'in:'.implode(',', self::NOTIFY_CHANNELS)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $reminder->update($data);

        return ApiResponse::success($this->serialize($reminder));
    }

    public function destroy(Request $request, string $id)
    {
        $reminder = $this->findOwnedReminder($request, $id);

        if (! $reminder) {
            return ApiResponse::error('NOT_FOUND', 'Reminder not found.', status: 404);
        }

        $reminder->delete();

        return ApiResponse::success();
    }

    private function findOwnedReminder(Request $request, string $id): ?Reminder
    {
        $group = $this->familyGroups->ensureForUser($request->user());

        return Reminder::query()->where('id', $id)->where('family_group_id', $group->id)->first();
    }

    private function serialize(Reminder $reminder): array
    {
        return [
            'id' => $reminder->id,
            'member_id' => $reminder->member_id,
            'reminder_type' => $reminder->reminder_type,
            'title' => $reminder->title,
            'description' => $reminder->description,
            'due_at' => $reminder->due_at->toIso8601String(),
            'recurrence' => $reminder->recurrence,
            'notify_via' => $reminder->notify_via,
            'is_active' => $reminder->is_active,
            'last_sent_at' => $reminder->last_sent_at?->toIso8601String(),
            'linked_record_id' => $reminder->linked_record_id,
            'created_at' => $reminder->created_at->toIso8601String(),
        ];
    }
}
