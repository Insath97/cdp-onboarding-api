<?php

use App\Enums\CandidateStatus;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $department = Department::create([
        'name' => 'Portal Test Department',
        'code' => 'PTD-'.uniqid(),
    ]);

    $this->designation = Designation::create([
        'department_id' => $department->id,
        'name' => 'Portal Test Designation',
        'code' => 'PTDsg-'.uniqid(),
        'level' => 'mid',
    ]);

    $this->payload = [
        'first_name' => 'Portal',
        'last_name' => 'Candidate',
        'nic_number' => '99'.random_int(1000000, 9999999).'V',
        'phone_number' => '0771234567',
        'designation_id' => $this->designation->id,
        'expected_salary' => 150000,
        'uploader_comments' => 'Applied via web portal',
        'cv_file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
    ];
});

it('submits a candidate cv through the public portal endpoint without auth', function () {
    $response = $this->post('/api/v1/portal/candidates', $this->payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => ['id', 'first_name', 'last_name', 'nic_number', 'designation_id'],
        ])
        ->assertJsonPath('status', 'success');

    $candidate = Candidate::where('nic_number', $this->payload['nic_number'])->first();

    expect($candidate)->not->toBeNull()
        ->and($candidate->source)->toBe(Candidate::SOURCE_DIRECT)
        ->and($candidate->status)->toBe(CandidateStatus::PENDING)
        ->and($candidate->uploaded_by)->toBeNull()
        ->and($candidate->channel_head_employee_id)->toBeNull()
        ->and($candidate->submitted_at)->not->toBeNull()
        ->and(str_starts_with($candidate->cv_path, 'uploads/candidates/cv_'))->toBeTrue();
});

it('returns 422 with field errors when required attributes are missing', function () {
    $response = $this->postJson('/api/v1/portal/candidates', []);

    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => [['field', 'messages']]]);
});

it('rejects invalid cv file types on the portal endpoint', function () {
    $payload = $this->payload;
    $payload['cv_file'] = UploadedFile::fake()->create('malware.txt', 10, 'text/plain');

    $response = $this->post('/api/v1/portal/candidates', $payload);

    $response->assertStatus(422);

    $errors = collect($response->json('errors'));
    expect($errors->pluck('field')->contains('cv_file'))->toBeTrue();
});

it('blocks duplicate nic with an in-progress application on the portal endpoint', function () {
    Candidate::create([
        'first_name' => 'Existing',
        'last_name' => 'Candidate',
        'nic_number' => $this->payload['nic_number'],
        'phone_number' => '0771234567',
        'designation_id' => $this->designation->id,
        'expected_salary' => 100000,
        'cv_path' => 'uploads/candidates/cv_existing.pdf',
        'status' => CandidateStatus::PENDING->value,
        'source' => Candidate::SOURCE_DIRECT,
        'submitted_at' => now(),
    ]);

    $response = $this->post('/api/v1/portal/candidates', $this->payload);

    $response->assertStatus(422);
});
