<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <x-admin.site-switcher />

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @can('review-content')
                        <flux:sidebar.item icon="inbox-stack" :href="route('admin.review')" :current="request()->routeIs('admin.review')" wire:navigate>
                            {{ __('Review queue') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                @canany(['posts.view', 'pages.view', 'categories.view', 'media.view'])
                    <flux:sidebar.group :heading="__('Content')" class="grid">
                        @can('posts.view')
                            <flux:sidebar.item icon="newspaper" :href="route('admin.posts.index')" :current="request()->routeIs('admin.posts.*')" wire:navigate>
                                {{ __('Posts') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('pages.view')
                            <flux:sidebar.item icon="document-text" :href="route('admin.pages.index')" :current="request()->routeIs('admin.pages.*')" wire:navigate>
                                {{ __('Pages') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('categories.view')
                            <flux:sidebar.item icon="tag" :href="route('admin.categories')" :current="request()->routeIs('admin.categories')" wire:navigate>
                                {{ __('Categories') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('media.view')
                            <flux:sidebar.item icon="photo" :href="route('admin.media')" :current="request()->routeIs('admin.media')" wire:navigate>
                                {{ __('Media library') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany

                @canany(['menus.view', 'sites.update'])
                    <flux:sidebar.group :heading="__('Website')" class="grid">
                        @can('menus.view')
                            <flux:sidebar.item icon="bars-3" :href="route('admin.menus')" :current="request()->routeIs('admin.menus')" wire:navigate>
                                {{ __('Menus') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('sites.update')
                            <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.site-settings')" :current="request()->routeIs('admin.site-settings')" wire:navigate>
                                {{ __('Site settings') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany

                @canany(['users.view', 'roles.view', 'audit_logs.view'])
                    <flux:sidebar.group :heading="__('System')" class="grid">
                        @can('users.view')
                            <flux:sidebar.item icon="users" :href="route('admin.users.index')" :current="request()->routeIs('admin.users.*')" wire:navigate>
                                {{ __('Users') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('roles.view')
                            <flux:sidebar.item icon="shield-check" :href="route('admin.roles')" :current="request()->routeIs('admin.roles')" wire:navigate>
                                {{ __('Roles & permissions') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('audit_logs.view')
                            <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.audit-logs')" :current="request()->routeIs('admin.audit-logs')" wire:navigate>
                                {{ __('Audit logs') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
