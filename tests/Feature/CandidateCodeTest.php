<?php

use App\Models\Candidate;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $department = Department::create([
        'name' => 'Code Test Department',
        'code' => 'CTD-' . uniqid(),
    ]);

    $this->designation = Designation::create([
        'department_id' => $department->id,
        'name' => 'Code Test Designation',
        'code' => 'CTDsg-' . uniqid(),
        'level' => 'mid',
    ]);

    $this->newCandidate = function (string $source, ?string $nic = null): Candidate {
        return Candidate::create([
            'first_name'      => 'Code',
            'last_name'       => 'Test',
            'nic_number'      => $nic ?? random_int(100000000, 999999999) . 'V',
            'phone_number'    => '0771234567',
            'designation_id'  => $this->designation->id,
            'expected_salary' => 100000,
            'cv_path'         => 'uploads/candidates/cv_test.pdf',
            'status'          => 'pending',
            'source'          => $source,
            'submitted_at'    => now(),
        ]);
    };

    $this->portalPayload = function (): array {
        return [
            'first_name'       => 'Portal',
            'last_name'        => 'Coded',
            'nic_number'       => random_int(100000000, 999999999) . 'V',
            'phone_number'     => '0771234567',
            'designation_id'   => $this->designation->id,
            'expected_salary'  => 150000,
            'cv_file'          => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ];
    };
});

it('generates a DIR prefixed code for direct source', function () {
    $candidate = ($this->newCandidate)(Candidate::SOURCE_DIRECT);

    $expected = 'DIR' . now()->format('ym') . '0001';
    expect($candidate->candidate_code)->toBe($expected);
});

it('generates an R prefixed code for referral source', function () {
    $candidate = ($this->newCandidate)(Candidate::SOURCE_REFERRAL);

    $expected = 'R' . now()->format('ym') . '0001';
    expect($candidate->candidate_code)->toBe($expected);
});

it('increments the sequence for subsequent candidates in the same month', function () {
    $first = ($this->newCandidate)(Candidate::SOURCE_DIRECT);
    $second = ($this->newCandidate)(Candidate::SOURCE_DIRECT);
    $third = ($this->newCandidate)(Candidate::SOURCE_DIRECT);

    $ym = now()->format('ym');
    expect($first->candidate_code)->toBe("DIR{$ym}0001")
        ->and($second->candidate_code)->toBe("DIR{$ym}0002")
        ->and($third->candidate_code)->toBe("DIR{$ym}0003");
});

it('keeps independent sequences per source', function () {
    $direct = ($this->newCandidate)(Candidate::SOURCE_DIRECT);
    $referral = ($this->newCandidate)(Candidate::SOURCE_REFERRAL);
    $anotherDirect = ($this->newCandidate)(Candidate::SOURCE_DIRECT);

    $ym = now()->format('ym');
    expect($direct->candidate_code)->toBe("DIR{$ym}0001")
        ->and($referral->candidate_code)->toBe("R{$ym}0001")
        ->and($anotherDirect->candidate_code)->toBe("DIR{$ym}0002");
});

it('ignores a client-supplied candidate_code and generates its own', function () {
    $candidate = Candidate::create([
        'first_name'      => 'Fake',
        'last_name'       => 'Code',
        'nic_number'      => random_int(100000000, 999999999) . 'V',
        'phone_number'    => '0771234567',
        'designation_id'  => $this->designation->id,
        'expected_salary' => 100000,
        'cv_path'         => 'uploads/candidates/cv_test.pdf',
        'status'          => 'pending',
        'source'          => Candidate::SOURCE_DIRECT,
        'submitted_at'    => now(),
        'candidate_code'  => 'FAKE123',
    ]);

    expect($candidate->candidate_code)->not->toBe('FAKE123')
        ->and($candidate->candidate_code)->toMatch('/^DIR\d{8}$/');
});

it('returns candidate_code in the public portal submission response', function () {
    $response = $this->post('/api/v1/portal/candidates', ($this->portalPayload)());

    $response->assertStatus(201);

    $code = $response->json('data.candidate_code');
    expect($code)->toMatch('/^DIR\d{8}$/');
});
