# Phase 5 — AI assistant

Natural language capture and Q&A, with confirmation before writes.

## Rules

1. **Parse** returns strict JSON (validated) before anything is shown.
2. Critical fields (dates, amounts, recipients) are never invented — they go in `missing_fields`.
3. A **confirmation card** is required when confidence is low, fields are missing, or the action is destructive.
4. The assistant **reads only through server-side tools** scoped to the signed-in user.
5. Titles/notes/imported text are **untrusted** and must not change assistant behaviour.
6. Provider, model, and keys come from `.env`. Per-user rate limits and a monthly request cap apply.

## Voice

Speech-to-text uses a configured provider (`SPEECH_TO_TEXT_PROVIDER=openai` + API key → Whisper). Browser speech recognition is not used (unreliable in Android WebView and Windows WebView2). Transcripts go through the same parse → confirm flow.

## Without an API key

A local heuristic parser still handles common Nigerian English capture phrases (pay/bill amounts, monthly due days, call/buy tasks) so tests and local demos work. Set `AI_PROVIDER` / `AI_API_KEY` / `AI_MODEL` for LLM-assisted parsing and answers.

## API

| Endpoint | Purpose |
| --- | --- |
| `POST /api/assistant/parse` | Text → proposal JSON |
| `POST /api/assistant/confirm` | Confirm proposal → create activity |
| `POST /api/assistant/ask` | Question → tool-backed answer |
| `POST /api/assistant/transcribe` | Audio → text (provider required) |

## Env

```
AI_PROVIDER=openai
AI_API_KEY=
AI_MODEL=gpt-4o-mini
AI_MONTHLY_REQUEST_CAP=200
AI_RATE_PER_MINUTE=10
SPEECH_TO_TEXT_PROVIDER=openai
SPEECH_TO_TEXT_API_KEY=
```
