<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'student',
            'section' => 'Section 1',
            'course' => 'Mathematics',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $this->assertNotNull($user->student_id_no);
        $this->assertMatchesRegularExpression('/^\d{10}$/', (string) $user->student_id_no);
        $this->assertStringStartsWith((string) now()->year, (string) $user->student_id_no);
    }

    public function test_student_registration_generates_unique_student_id_numbers()
    {
        $first = $this->post(route('register.store'), [
            'name' => 'Student One',
            'email' => 'student1@example.com',
            'role' => 'student',
            'section' => 'Section 1',
            'course' => 'Mathematics',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $first->assertRedirect(route('dashboard', absolute: false));

        auth()->logout();

        $second = $this->post(route('register.store'), [
            'name' => 'Student Two',
            'email' => 'student2@example.com',
            'role' => 'student',
            'section' => 'Section 2',
            'course' => 'Science',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $second->assertRedirect(route('dashboard', absolute: false));

        $firstUser = User::query()->where('email', 'student1@example.com')->firstOrFail();
        $secondUser = User::query()->where('email', 'student2@example.com')->firstOrFail();
        $this->assertNotSame($firstUser->student_id_no, $secondUser->student_id_no);
    }
}
