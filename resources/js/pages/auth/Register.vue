<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { BookOpen, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

const selectedRole = ref('student');
</script>

<template>
    <AuthBase
        title="Create an account"
        description="Enter your details below to create your account"
    >
        <Head title="Register" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="Full name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirm password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div class="grid gap-3">
                    <Label>Account Type</Label>
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Student Option -->
                        <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer transition-all" :class="selectedRole === 'student' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'">
                            <input
                                type="radio"
                                name="role"
                                value="student"
                                v-model="selectedRole"
                                class="w-4 h-4"
                            />
                            <div class="ml-3">
                                <Users class="w-5 h-5 mb-1" :class="selectedRole === 'student' ? 'text-blue-600' : 'text-gray-600'" />
                                <div class="font-medium text-sm" :class="selectedRole === 'student' ? 'text-blue-900' : 'text-gray-900'">Student</div>
                                <div class="text-xs text-gray-600">Learn and join classes</div>
                            </div>
                        </label>

                        <!-- Teacher Option -->
                        <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer transition-all" :class="selectedRole === 'teacher' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'">
                            <input
                                type="radio"
                                name="role"
                                value="teacher"
                                v-model="selectedRole"
                                class="w-4 h-4"
                            />
                            <div class="ml-3">
                                <BookOpen class="w-5 h-5 mb-1" :class="selectedRole === 'teacher' ? 'text-blue-600' : 'text-gray-600'" />
                                <div class="font-medium text-sm" :class="selectedRole === 'teacher' ? 'text-blue-900' : 'text-gray-900'">Teacher</div>
                                <div class="text-xs text-gray-600">Create and manage courses</div>
                            </div>
                        </label>
                    </div>
                    <InputError :message="errors.role" />
                </div>

                <div v-if="selectedRole === 'student'" class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="section">Section</Label>
                        <select
                            id="section"
                            name="section"
                            required
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                        >
                            <option value="">Select section</option>
                            <option value="Section 1">Section 1</option>
                            <option value="Section 2">Section 2</option>
                            <option value="Section 3">Section 3</option>
                        </select>
                        <InputError :message="errors.section" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="course">Course</Label>
                        <select
                            id="course"
                            name="course"
                            required
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                        >
                            <option value="">Select course</option>
                            <option value="Mathematics">Mathematics</option>
                            <option value="Science">Science</option>
                            <option value="English">English</option>
                        </select>
                        <InputError :message="errors.course" />
                    </div>
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="5"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Create account
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="6"
                    >Log in</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
