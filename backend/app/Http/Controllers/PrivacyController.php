<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteAccountRequest;
use App\Services\PrivacyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyController extends Controller
{
    public function notice(): JsonResponse
    {
        return response()->json([
            'data' => [
                'title' => 'Privacy notice',
                'version' => '2026-09-23',
                'summary' => 'GIT Life Assistant stores the personal data you enter (account details, activities, contacts, shopping lists, devices, and preferences) so the app can remind you and sync across your devices. Amounts are planned/expected figures, not bank transactions. We do not store card numbers, CVV, PINs, or bank passwords. You may export or delete your account at any time. Processing is for providing the service under the Nigeria Data Protection Act 2023.',
            ],
        ]);
    }

    public function accept(Request $request, PrivacyService $privacy): Response
    {
        $privacy->acceptPrivacyNotice($request->user());

        return response()->noContent();
    }

    public function export(Request $request, PrivacyService $privacy): StreamedResponse
    {
        $payload = $privacy->export($request->user());
        $filename = 'gitlife-export-'.$request->user()->id.'.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function destroy(DeleteAccountRequest $request, PrivacyService $privacy): Response
    {
        $privacy->deleteAccount($request->user(), $request->string('password')->toString());

        return response()->noContent();
    }
}
