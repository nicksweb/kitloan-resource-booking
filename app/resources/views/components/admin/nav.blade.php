@php
    $links = [];
    if (Gate::allows('manage-catalog')) {
        $links[] = ['route' => 'admin.resource-pools.index', 'pattern' => 'admin.resource-pools.*', 'label' => 'Resource Pools'];
        $links[] = ['route' => 'admin.locations.index', 'pattern' => 'admin.locations.*', 'label' => 'Locations'];
        $links[] = ['route' => 'admin.booking-types.index', 'pattern' => 'admin.booking-types.*', 'label' => 'Booking Types'];
        $links[] = ['route' => 'admin.periods.index', 'pattern' => 'admin.periods.*', 'label' => 'Periods'];
        $links[] = ['route' => 'admin.integrations.snipeit', 'pattern' => 'admin.integrations.*', 'label' => 'Snipe-IT'];
    }
    if (Gate::allows('manage-users')) {
        $links[] = ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'label' => 'Users'];
    }
    if (Gate::allows('manage-settings')) {
        $links[] = ['route' => 'admin.settings.index', 'pattern' => 'admin.settings.*', 'label' => 'Settings'];
        $links[] = ['route' => 'admin.message-templates.index', 'pattern' => 'admin.message-templates.*', 'label' => 'Emails'];
    }
    if (Gate::allows('view-reports')) {
        $links[] = ['route' => 'admin.reports.index', 'pattern' => 'admin.reports.*', 'label' => 'Reports'];
    }
    if (Gate::allows('view-audit-log')) {
        $links[] = ['route' => 'admin.audit-log.index', 'pattern' => 'admin.audit-log.*', 'label' => 'Audit Log'];
    }
@endphp
<div class="mb-6 border-b border-gray-200">
    <nav class="-mb-px flex items-center gap-6 overflow-x-auto">
        @foreach ($links as $link)
            <a href="{{ route($link['route']) }}"
               class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium {{ request()->routeIs($link['pattern']) ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                {{ $link['label'] }}
            </a>
        @endforeach
        <span class="ml-auto whitespace-nowrap py-3 text-xs text-gray-400">Kitloan v{{ config('version.app') }}</span>
    </nav>
</div>
