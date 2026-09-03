<?php

use App\Models\Application;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 1. Create permissions and role for GIAM and client application
    $this->appPermission = Permission::create([
        'name' => 'customer.view',
        'guard_name' => 'api',
        'group_name' => 'Test Group'
    ]);

    $this->clientApp = Application::create([
        'name' => 'Test Client App',
        'code' => 'testapp',
        'is_active' => true
    ]);

    // Update the permission application scoping
    $this->appPermission->update(['application_id' => $this->clientApp->id]);

    $this->clientRole = Role::create([
        'name' => 'App Officer',
        'guard_name' => 'api',
        'application_id' => $this->clientApp->id
    ]);
    $this->clientRole->syncPermissions([$this->appPermission]);

    // Create a global staff role
    $this->staffRole = Role::create([
        'name' => 'Staff',
        'guard_name' => 'api'
    ]);

    // 2. Create testing user
    $this->user = User::create([
        'name' => 'John Doe',
        'username' => 'johndoe',
        'email' => 'john@test.com',
        'password' => Hash::make('password123'),
        'user_type' => 'admin',
        'is_active' => true,
        'can_login' => true
    ]);

    // Assign global role to user
    $this->user->assignRole($this->staffRole);

    // Assign application to user
    $this->user->applications()->sync([$this->clientApp->id]);

    // Assign application role to user
    $this->user->syncRolesForApplication([$this->clientRole->id], $this->clientApp->id);
});

it('validates a valid token and returns scoped permissions', function () {
    // Generate valid token
    $token = JWTAuth::fromUser($this->user);

    $response = $this->postJson('/api/v1/sso/introspect', [
        'token' => $token,
        'application_code' => 'testapp'
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('active', true)
        ->assertJsonPath('data.user.username', 'johndoe')
        ->assertJsonPath('data.roles', ['App Officer'])
        ->assertJsonPath('data.permissions', ['customer.view']);
});

it('rejects an invalid token', function () {
    $response = $this->postJson('/api/v1/sso/introspect', [
        'token' => 'invalid.jwt.token',
        'application_code' => 'testapp'
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('active', false)
        ->assertJsonPath('status', 'error');
});

it('rejects an validation if application does not exist', function () {
    $token = JWTAuth::fromUser($this->user);

    $response = $this->postJson('/api/v1/sso/introspect', [
        'token' => $token,
        'application_code' => 'nonexistentapp'
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors']);
});

it('rejects introspection if application is deactivated', function () {
    $token = JWTAuth::fromUser($this->user);

    $this->clientApp->update(['is_active' => false]);

    $response = $this->postJson('/api/v1/sso/introspect', [
        'token' => $token,
        'application_code' => 'testapp'
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('active', false)
        ->assertJsonPath('message', 'Application is deactivated');
});

it('rejects introspection if user does not have access to application', function () {
    $token = JWTAuth::fromUser($this->user);

    // Detach application from user
    $this->user->applications()->detach($this->clientApp->id);

    $response = $this->postJson('/api/v1/sso/introspect', [
        'token' => $token,
        'application_code' => 'testapp'
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('active', false)
        ->assertJsonPath('message', 'User is not assigned to this application');
});

it('rejects introspection if user account is deactivated', function () {
    $token = JWTAuth::fromUser($this->user);

    $this->user->update(['is_active' => false]);

    $response = $this->postJson('/api/v1/sso/introspect', [
        'token' => $token,
        'application_code' => 'testapp'
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('active', false)
        ->assertJsonPath('message', 'User account is deactivated');
});
