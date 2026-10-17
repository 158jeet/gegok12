<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Models\TagoreAdmissionLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OlympiadRegistrationController extends Controller
{
    private const PRIVACY_NOTICE_VERSION = '2026-10-13';

    public function create(Request $request): View
    {
        abort_unless((bool) config('services.olympiad.enabled'), 404);
        $institutionId = (int) config('services.olympiad.institution_id');
        $intakeReady = $institutionId > 0 && DB::table('tagore_institutions')
            ->where('id', $institutionId)->where('status', 'active')->exists();

        return view('olympiad.register', [
            'intakeReady' => $intakeReady,
            'utm' => [
                'utm_source' => $request->query('utm_source', 'olympiad'),
                'utm_medium' => $request->query('utm_medium'),
                'utm_campaign' => $request->query('utm_campaign', $request->query('campaign')),
                'utm_content' => $request->query('utm_content'),
                'utm_term' => $request->query('utm_term'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless((bool) config('services.olympiad.enabled'), 404);
        abort_if($request->filled('website'), 422);

        $data = $request->validate([
            'student_name' => ['required', 'string', 'max:150'],
            'parent_name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\\s.-]{8,30}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'class_name' => ['required', 'in:4,5,6,7,8,9,10,11,12'],
            'medium' => ['required', 'in:english,hindi'],
            'school_name' => ['nullable', 'string', 'max:190'],
            'city' => ['nullable', 'string', 'max:120'],
            'campaign' => ['nullable', 'string', 'max:100'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_content' => ['nullable', 'string', 'max:150'],
            'utm_term' => ['nullable', 'string', 'max:150'],
            'marketing_consent' => ['sometimes', 'accepted'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        $institutionId = (int) config('services.olympiad.institution_id');
        abort_unless($institutionId > 0 && DB::table('tagore_institutions')
            ->where('id', $institutionId)->where('status', 'active')->exists(), 503);

        $consent = $request->boolean('marketing_consent');
        $campaign = $data['campaign'] ?? $data['utm_campaign'] ?? null;

        $lead = DB::transaction(function () use ($data, $institutionId, $consent, $campaign) {
            do {
                $reference = 'OLY-' . Str::upper(Str::random(10));
            } while (TagoreAdmissionLead::where('lead_no', $reference)->exists());

            return TagoreAdmissionLead::create([
                'institution_id' => $institutionId,
                'academic_year_id' => null,
                'lead_no' => $reference,
                'student_name' => trim($data['student_name']),
                'parent_name' => trim($data['parent_name']),
                'mobile' => preg_replace('/\\s+/', '', trim($data['mobile'])),
                'email' => $data['email'] ?? null,
                'class_name' => $data['class_name'],
                'source' => 'olympiad',
                'campaign' => $campaign,
                'status' => 'new',
                'medium' => $data['medium'],
                'school_name' => $data['school_name'] ?? null,
                'city' => $data['city'] ?? null,
                'marketing_consent' => $consent,
                'consented_at' => $consent ? now() : null,
                'privacy_notice_version' => self::PRIVACY_NOTICE_VERSION,
                'utm_source' => $data['utm_source'] ?? 'olympiad',
                'utm_medium' => $data['utm_medium'] ?? null,
                'utm_content' => $data['utm_content'] ?? null,
                'utm_term' => $data['utm_term'] ?? null,
            ]);
        });

        return redirect()->route('olympiad.registered')
            ->with('olympiad_reference', $lead->lead_no)
            ->with('olympiad_exam_url', config('services.olympiad.exam_url'));
    }

    public function registered(Request $request): View
    {
        abort_unless((bool) config('services.olympiad.enabled'), 404);
        $reference = $request->session()->get('olympiad_reference');
        abort_unless(is_string($reference) && str_starts_with($reference, 'OLY-'), 404);

        return view('olympiad.registered', [
            'reference' => $reference,
            'examUrl' => $request->session()->get('olympiad_exam_url'),
        ]);
    }
}