<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $resolvedRole = strtolower((string) ($input['role'] ?? 'student'));
        if (! in_array($resolvedRole, ['student', 'teacher'], true)) {
            $resolvedRole = 'student';
        }
        $input['role'] = $resolvedRole;

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', 'string', 'in:student,teacher'],
            'section' => ['nullable', 'string', 'max:255', 'required_if:role,student'],
            'course' => ['nullable', 'string', 'max:255', 'required_if:role,student'],
        ])->validate();

        $generatedStudentIdNo = $resolvedRole === 'student'
            ? User::generateUniqueStudentIdNo()
            : null;

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => $resolvedRole,
            'student_id_no' => $generatedStudentIdNo,
            'section' => $input['section'] ?? null,
            'course' => $input['course'] ?? null,
        ]);
    }
}
