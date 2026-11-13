<?php

use App\Models\DocumentType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'PermissionsSeeder']);

    $this->user = User::firstOrCreate(
        ['email' => 'admin@test.com'],
        [
            'name' => 'Super Admin User',
            'username' => 'superadmin_test',
            'password' => bcrypt('password'),
            'user_type' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]
    );

    $superAdminRole = Role::where('name', 'Super Admin')->where('guard_name', 'api')->first();
    if ($superAdminRole && ! $this->user->hasRole($superAdminRole)) {
        $this->user->assignRole($superAdminRole);
    }

    $this->token = JWTAuth::fromUser($this->user);
});

afterEach(function () {
    DocumentType::where('document_name', 'like', '%Test%')
        ->orWhere('document_name', 'like', '%Document%')
        ->orWhere('document_name', 'like', '%Updated%')
        ->delete();
});

it('can list document types with authentication', function () {
    DocumentType::create(['document_name' => 'Listed Document Test', 'description' => 'Test document', 'is_active' => true]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/document-types');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'data',
                'current_page',
            ],
        ]);
});

it('can create a document type with auto-generated slug', function () {
    $payload = [
        'document_name' => 'Aadhaar Card Test',
        'description' => 'Government ID proof',
        'is_required' => true,
        'is_active' => true,
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/document-types', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'Document type created successfully',
            'data' => [
                'document_name' => 'Aadhaar Card Test',
                'slug' => 'aadhaar-card-test',
                'is_required' => true,
                'is_active' => true,
            ],
        ]);

    $this->assertDatabaseHas('document_types', [
        'document_name' => 'Aadhaar Card Test',
        'slug' => 'aadhaar-card-test',
    ]);
});

it('fails validation when creating with duplicate document name', function () {
    DocumentType::create(['document_name' => 'Duplicate Document Test', 'is_active' => true]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/document-types', [
            'document_name' => 'Duplicate Document Test',
        ]);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'errors',
        ]);
});

it('can get active list of document types', function () {
    DocumentType::create(['document_name' => 'Active Document Test', 'is_active' => true]);
    DocumentType::create(['document_name' => 'Inactive Document Test', 'is_active' => false]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/document-types/list');

    $response->assertStatus(200)
        ->assertJson(['status' => 'success']);

    $data = $response->json('data');
    expect(collect($data)->pluck('document_name')->contains('Active Document Test'))->toBeTrue();
    expect(collect($data)->pluck('document_name')->contains('Inactive Document Test'))->toBeFalse();
});

it('can toggle document type status', function () {
    $documentType = DocumentType::create(['document_name' => 'Toggle Status Test', 'is_active' => true]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson("/api/v1/document-types/{$documentType->id}/toggle-status");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'id' => $documentType->id,
                'is_active' => false,
            ],
        ]);

    expect($documentType->fresh()->is_active)->toBeFalse();
});

it('can update a document type', function () {
    $documentType = DocumentType::create(['document_name' => 'Initial Name Test', 'is_active' => true]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson("/api/v1/document-types/{$documentType->id}", [
            'document_name' => 'Updated Name Test',
            'is_required' => true,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'document_name' => 'Updated Name Test',
                'is_required' => true,
            ],
        ]);
});

it('can delete a document type', function () {
    $documentType = DocumentType::create(['document_name' => 'To Delete Test', 'is_active' => true]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->deleteJson("/api/v1/document-types/{$documentType->id}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
        ]);

    $this->assertDatabaseMissing('document_types', [
        'id' => $documentType->id,
    ]);
});
