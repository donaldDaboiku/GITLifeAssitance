<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $contacts = Contact::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return ContactResource::collection($contacts);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $request->user()->contacts()->create($request->validated());

        return (new ContactResource($contact))->response()->setStatusCode(201);
    }

    public function show(Request $request, Contact $contact): ContactResource
    {
        abort_unless($contact->user_id === $request->user()->id, 403);

        return new ContactResource($contact);
    }

    public function update(UpdateContactRequest $request, Contact $contact): ContactResource
    {
        abort_unless($contact->user_id === $request->user()->id, 403);
        $contact->update($request->validated());

        return new ContactResource($contact);
    }

    public function destroy(Request $request, Contact $contact): Response
    {
        abort_unless($contact->user_id === $request->user()->id, 403);
        $contact->delete();

        return response()->noContent();
    }
}
