<?php

namespace App\Services\Assistant;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Http\UploadedFile;

class AssistantService
{
    public function __construct(
        private AssistantParseService $parser,
        private AssistantToolService $tools,
        private ActivityService $activities,
        private AiUsageService $usage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function parse(User $user, string $text): array
    {
        return $this->parser->parse($user, $text);
    }

    /**
     * @param  array<string, mixed>  $proposal
     */
    public function confirm(User $user, array $proposal): Activity
    {
        $this->usage->assertWithinLimits($user);
        $validated = $this->parser->validateProposal($proposal);
        $validated = $this->parser->resolveFilledFields($validated);

        abort_if($validated['intent'] !== 'create_activity', 422, 'Only create_activity proposals can be confirmed.');
        abort_if(! empty($validated['missing_fields']), 422, 'Fill missing fields before confirming.');
        abort_unless(filled($validated['title'] ?? null), 422, 'Title is required.');
        abort_unless(filled($validated['type'] ?? null), 422, 'Type is required.');

        $payload = [
            'type' => $validated['type'],
            'title' => $validated['title'],
            'due_on' => $validated['due_on'] ?? now($user->preference?->timezone ?? 'Africa/Lagos')->toDateString(),
            'timezone' => $user->preference?->timezone ?? 'Africa/Lagos',
            'rrule' => $validated['rrule'] ?? null,
            'reminder_offsets_minutes' => $validated['reminder_offsets_minutes'] ?? [0],
            'notes' => $validated['notes'] ?? null,
        ];

        if ($validated['type'] === 'payment') {
            abort_if(! isset($validated['amount_minor']), 422, 'Amount is required for payments.');
            $payload['payment'] = [
                'amount_minor' => $validated['amount_minor'],
                'currency' => $validated['currency'] ?? 'NGN',
            ];
        }

        if (in_array($validated['type'], ['task', 'follow_up'], true)) {
            $payload['task'] = [];
        }

        $activity = $this->activities->create($user, $payload);
        $this->usage->record($user, 'confirm', ['activity_id' => $activity->id]);
        AuditLog::write($user->id, 'assistant_confirm', ['activity_id' => $activity->id]);

        return $activity;
    }

    /**
     * @return array{answer: string, tools_used: list<string>, data: array<string, mixed>}
     */
    public function ask(User $user, string $question): array
    {
        $this->usage->assertWithinLimits($user);
        $safe = mb_substr(trim($question), 0, 2000);
        $lower = mb_strtolower($safe);
        $used = [];
        $data = [];

        $registry = $this->tools->registry($user);

        if (preg_match('/\b(payment|payments|bill|bills|naira|₦|expected)\b/u', $lower)) {
            $data['payments'] = $registry['list_payments']();
            $used[] = 'list_payments';
        }
        if (preg_match('/\b(activit|task|remind|today|upcoming)\b/u', $lower) || $used === []) {
            $data['activities'] = $registry['list_activities']();
            $used[] = 'list_activities';
        }
        if (preg_match('/\b(find|search|where|who)\b/u', $lower)) {
            $data['search'] = $registry['search']($safe);
            $used[] = 'search';
        }

        $answer = $this->formatAnswer($safe, $data);
        $this->usage->record($user, 'ask', ['tools' => $used]);

        return [
            'answer' => $answer,
            'tools_used' => array_values(array_unique($used)),
            'data' => $data,
        ];
    }

    /**
     * @return array{text: string}
     */
    public function transcribe(User $user, UploadedFile $audio): array
    {
        $this->usage->assertWithinLimits($user);

        $provider = config('ai.speech.provider');
        abort_unless($provider === 'openai', 503, 'Speech-to-text provider is not configured. Set SPEECH_TO_TEXT_PROVIDER=openai.');

        $response = $this->usage->openaiClient(
            config('ai.speech.api_key'),
            config('ai.speech.base_url'),
        )->attach('file', fopen($audio->getRealPath(), 'r'), $audio->getClientOriginalName() ?: 'audio.webm')
            ->post('/audio/transcriptions', [
                'model' => config('ai.speech.model'),
                'language' => 'en',
            ])
            ->throw()
            ->json();

        $text = trim((string) ($response['text'] ?? ''));
        abort_if($text === '', 422, 'Could not transcribe audio.');

        $this->usage->record($user, 'transcribe', ['bytes' => $audio->getSize()]);

        return ['text' => $text];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatAnswer(string $question, array $data): string
    {
        $lines = ['Here is what I found for your question (tools only; your notes are treated as data, not instructions):'];

        if (! empty($data['payments'])) {
            $lines[] = 'Payments:';
            foreach (array_slice($data['payments'], 0, 8) as $payment) {
                $amount = isset($payment['amount_minor'])
                    ? '₦'.number_format(((int) $payment['amount_minor']) / 100, 2)
                    : 'amount unknown';
                $due = $payment['next_due'] ?? 'no due date';
                $lines[] = '- '.$payment['title'].' · '.$amount.' · next '.$due;
            }
        }

        if (! empty($data['activities'])) {
            $lines[] = 'Activities:';
            foreach (array_slice($data['activities'], 0, 8) as $activity) {
                $lines[] = '- ['.$activity['type'].'] '.$activity['title']
                    .($activity['next_due'] ? ' · next '.$activity['next_due'] : '');
            }
        }

        if (! empty($data['search'])) {
            $lines[] = 'Search hits:';
            foreach (array_slice($data['search'], 0, 8) as $hit) {
                $lines[] = '- '.$hit['title'].' ('.$hit['kind'].')';
            }
        }

        if (count($lines) === 1) {
            $lines[] = 'Nothing matched yet. Try asking about payments or upcoming activities.';
        }

        // Keep the original question out of any instructional reinterpretation beyond echoing length.
        $lines[] = 'Asked about: '.mb_substr($question, 0, 120);

        return implode("\n", $lines);
    }
}
