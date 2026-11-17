@props(['name'])
@error($name)
    <p class="mt-1 text-sm whitespace-pre-line text-red-600">{{ $message }}</p>
@enderror
