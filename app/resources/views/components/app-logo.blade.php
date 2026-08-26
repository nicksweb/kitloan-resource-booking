@props([
    'iconClass' => 'w-7 h-7 text-indigo-600',
    'imgClass' => 'h-8 w-auto',
])

@php($siteLogoPath = app(\App\Settings\SettingsRepository::class)->get('site_logo_path'))

@if ($siteLogoPath)
    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($siteLogoPath) }}"
         alt="{{ config('app.name') }}" class="{{ $imgClass }}">
@else
    <x-laptop-icon :class="$iconClass" />
@endif
