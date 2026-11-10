<?php

use App\Enums\CandidateStatus;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\Department;
use App\Models\Designation;
use App\Models\DocumentType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('private');

    // Seed permissions & roles
    $this->artisan('db:seed', ['--class' => 'PermissionsSeeder']);

    $department = Department::create([
        'name' => 'Doc Flow Department',
        'code' => 'DFD-' . uniqid(),
    ]);

    $this->designation = Designation::create([
        'department_id' => $department->id,
        'name'          => 'Doc Flow Designation',
        'code'          => 'DFDsg-' . uniqid(),
        'level'         => 'mid',
    ]);

    // Create required & optional document types
    $this->reqDocType = DocumentType::create([
        'document_name' => 'National Identity Card (NIC)',
        'slug'          => 'nic-copy',
        'is_required'   => true,
        'is_active'     => true,
    ]);

    $this->optDocType = DocumentType::create([
        'document_name' => 'Experience Certificate',
        'slug'          => 'exp-cert',
        'is_required'   => false,
        'is_active'     => true,
    ]);

    // Create Super Admin user
    $this->staffUser = User::create([
        'name'      => 'Staff Recruiter',
        'email'     => 'recruiter@localhost.com',
        'username'  => 'recruiter_' . uniqid(),
        'password'  => bcrypt('password'),
        'user_type' => 'staff',
        'is_active' => true,
        'can_login' => true,
    ]);
    $this->staffUser->assignRole('Super Admin');
    $this->staffToken = JWTAuth::fromUser($this->staffUser);
});

it('auto-generates candidate code and upload token on CV submission', function () {
    $payload = [
        'first_name'      => 'Kamal',
        'last_name'       => 'Perera',
        'nic_number'      => '912345678V',
        'phone_number'    => '0771234567',
        'designation_id'  => $this->designation->id,
        'expected_salary' => 120000,
        'cv_file'         => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
    ];

    $response = $this->post('/api/v1/portal/candidates', $payload);

    $response->assertStatus(201);
    $candidate = Candidate::where('nic_number', '912345678V')->first();

    expect($candidate)->not->toBeNull()
        ->and($candidate->status)->toBe(CandidateStatus::PENDING)
        ->and($candidate->candidate_code)->not->toBeNull()
        ->and(str_starts_with($candidate->candidate_code, 'DIR' . now()->format('ym')))->toBeTrue()
        ->and($candidate->document_upload_token)->not->toBeNull();
});

it('validates upload link and enforces pending status', function () {
    $candidate = Candidate::create([
        'first_name'            => 'Nimal',
        'last_name'             => 'Silva',
        'nic_number'            => '881234567V',
        'phone_number'          => '0712345678',
        'designation_id'        => $this->designation->id,
        'expected_salary'       => 100000,
        'cv_path'               => 'uploads/cv.pdf',
        'status'                => CandidateStatus::PENDING,
        'source'                => Candidate::SOURCE_DIRECT,
        'submitted_at'          => now(),
        'document_upload_token' => 'valid-test-token-12345',
    ]);

    // Active pending link should succeed
    $resValid = $this->getJson("/api/v1/portal/documents/validate/{$candidate->document_upload_token}");
    $resValid->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.candidate_code', $candidate->candidate_code);

    // If candidate status is updated to document_submitted, link must be blocked with 403
    $candidate->status = CandidateStatus::DOCUMENT_SUBMITTED;
    $candidate->save();

    $resBlocked = $this->getJson("/api/v1/portal/documents/validate/{$candidate->document_upload_token}");
    $resBlocked->assertStatus(403);
});

it('verifies candidate NIC number and dispatches OTP', function () {
    $candidate = Candidate::create([
        'first_name'            => 'Sunil',
        'last_name'             => 'Fernando',
        'nic_number'            => '951234567V',
        'phone_number'          => '0779876543',
        'designation_id'        => $this->designation->id,
        'expected_salary'       => 90000,
        'cv_path'               => 'uploads/cv.pdf',
        'status'                => CandidateStatus::PENDING,
        'source'                => Candidate::SOURCE_DIRECT,
        'submitted_at'          => now(),
        'document_upload_token' => 'otp-token-xyz',
    ]);

    // Mismatched NIC must fail with 422
    $failRes = $this->postJson('/api/v1/portal/documents/request-otp', [
        'token'      => 'otp-token-xyz',
        'nic_number' => 'WRONG_NIC_123',
    ]);
    $failRes->assertStatus(422)
        ->assertJsonPath('message', 'The entered NIC number does not match our application records.');

    // Matching NIC succeeds and caches OTP
    $successRes = $this->postJson('/api/v1/portal/documents/request-otp', [
        'token'      => 'otp-token-xyz',
        'nic_number' => '951234567V',
    ]);
    $successRes->assertStatus(200)
        ->assertJsonPath('status', 'success');

    $cached = Cache::get('cand_doc_otp_otp-token-xyz');
    expect($cached)->not->toBeNull()
        ->and($cached['nic_number'])->toBe('951234567V');
});

it('verifies OTP and permits document upload & final submission', function () {
    $candidate = Candidate::create([
        'first_name'            => 'Amara',
        'last_name'             => 'Gunasekara',
        'nic_number'            => '961234567V',
        'phone_number'          => '0773344556',
        'designation_id'        => $this->designation->id,
        'expected_salary'       => 95000,
        'cv_path'               => 'uploads/cv.pdf',
        'status'                => CandidateStatus::PENDING,
        'source'                => Candidate::SOURCE_DIRECT,
        'submitted_at'          => now(),
        'document_upload_token' => 'amara-token-abc',
    ]);

    // Seed OTP in cache
    Cache::put('cand_doc_otp_amara-token-abc', [
        'otp'        => '654321',
        'nic_number' => '961234567V',
    ], now()->addMinutes(10));

    // Verify OTP
    $otpRes = $this->postJson('/api/v1/portal/documents/verify-otp', [
        'token' => 'amara-token-abc',
        'otp'   => '654321',
    ]);
    $otpRes->assertStatus(200);
    $sessionKey = $otpRes->json('data.session_key');
    expect($sessionKey)->not->toBeNull();

    // Check checklist - shows required document is not yet uploaded
    $chkRes = $this->getJson("/api/v1/portal/documents/checklist/amara-token-abc?session_key={$sessionKey}");
    $chkRes->assertStatus(200)
        ->assertJsonPath('data.total_required', 1)
        ->assertJsonPath('data.uploaded_required', 0)
        ->assertJsonPath('data.is_ready_for_submission', false);

    // Attempting final submission without uploading required doc fails
    $subFail = $this->postJson('/api/v1/portal/documents/submit-all', [
        'token'       => 'amara-token-abc',
        'session_key' => $sessionKey,
    ]);
    $subFail->assertStatus(422);

    // Upload required NIC document
    $uploadRes = $this->post('/api/v1/portal/documents/upload', [
        'token'            => 'amara-token-abc',
        'session_key'      => $sessionKey,
        'document_type_id' => $this->reqDocType->id,
        'file'             => UploadedFile::fake()->create('nic.pdf', 200, 'application/pdf'),
    ]);
    $uploadRes->assertStatus(200);

    // Final submit succeeds now that required document is uploaded
    $subSuccess = $this->postJson('/api/v1/portal/documents/submit-all', [
        'token'       => 'amara-token-abc',
        'session_key' => $sessionKey,
    ]);
    $subSuccess->assertStatus(200)
        ->assertJsonPath('data.status', CandidateStatus::DOCUMENT_SUBMITTED->value);

    $candidate->refresh();
    expect($candidate->status)->toBe(CandidateStatus::DOCUMENT_SUBMITTED)
        ->and($candidate->document_upload_token)->toBeNull();
});

it('blocks 1st interview until documents are verified by staff', function () {
    $candidate = Candidate::create([
        'first_name'      => 'Chathura',
        'last_name'       => 'Bandara',
        'nic_number'      => '971234567V',
        'phone_number'    => '0776655443',
        'designation_id'  => $this->designation->id,
        'expected_salary' => 110000,
        'cv_path'         => 'uploads/cv.pdf',
        'status'          => CandidateStatus::PENDING,
        'source'          => Candidate::SOURCE_DIRECT,
        'submitted_at'    => now(),
    ]);

    // 1. Assigning 1st interview on 'pending' candidate MUST fail with 422
    $assignPayload = [
        'assigned_to_user_id' => $this->staffUser->id,
        'assigned_date'       => now()->addDays(2)->format('Y-m-d'),
        'comments'            => 'Attempt 1st interview',
    ];

    $res1 = $this->withHeader('Authorization', "Bearer {$this->staffToken}")
        ->postJson("/api/v1/candidates/{$candidate->id}/assign-1st-interview", $assignPayload);
    $res1->assertStatus(422);

    // 2. Candidate uploads required doc and moves to document_submitted
    CandidateDocument::create([
        'candidate_id'     => $candidate->id,
        'document_type_id' => $this->reqDocType->id,
        'file_path'        => 'candidates/test/nic.pdf',
        'status'           => 'pending',
    ]);
    $candidate->status = CandidateStatus::DOCUMENT_SUBMITTED;
    $candidate->save();

    // Still cannot assign 1st interview while in document_submitted
    $res2 = $this->withHeader('Authorization', "Bearer {$this->staffToken}")
        ->postJson("/api/v1/candidates/{$candidate->id}/assign-1st-interview", $assignPayload);
    $res2->assertStatus(422);

    // 3. Staff verifies documents
    $verifyRes = $this->withHeader('Authorization', "Bearer {$this->staffToken}")
        ->postJson("/api/v1/candidates/{$candidate->id}/documents/verify");
    $verifyRes->assertStatus(200);

    $candidate->refresh();
    expect($candidate->status)->toBe(CandidateStatus::DOCUMENT_VERIFIED);

    // 4. Now 1st interview assignment SUCCEEDS!
    $res3 = $this->withHeader('Authorization', "Bearer {$this->staffToken}")
        ->postJson("/api/v1/candidates/{$candidate->id}/assign-1st-interview", $assignPayload);
    $res3->assertStatus(200)
        ->assertJsonPath('data.status', CandidateStatus::FIRST_INTERVIEW->value);

    $candidate->refresh();
    expect($candidate->status)->toBe(CandidateStatus::FIRST_INTERVIEW);
});
