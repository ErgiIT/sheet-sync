<x-layout title="Log in">
    <div class="mx-auto mt-16 max-w-sm rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="mb-6 text-lg font-semibold">{{ config('app.name') }}</h1>
        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input">
                <x-field-error name="email" />
            </div>
            <div>
                <label for="password" class="label">Password</label>
                <input id="password" name="password" type="password" required class="input">
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Remember me</label>
            <x-button class="w-full">Log in</x-button>
        </form>
    </div>
</x-layout>
