<x-admin::layouts.anonymous>
    <x-slot:title>
        Argosvig | @lang('admin::app.users.login.title')
    </x-slot>

    <div class="min-h-screen bg-[#020b22] lg:grid lg:grid-cols-[1.35fr_.65fr]">
        <section
            class="relative hidden min-h-screen overflow-hidden bg-[#020b22] bg-contain bg-center bg-no-repeat lg:flex lg:items-end"
            style="background-image: url('{{ vite()->asset('images/argosvig-banner.png') }}');"
            aria-label="Argosvig"
        >
            <div class="absolute inset-0 bg-gradient-to-t from-[#020b22] via-transparent to-transparent"></div>

            <div class="relative z-10 p-10 text-white xl:p-14">
                <p class="text-xs font-semibold uppercase tracking-[0.32em] text-blue-300">Portal corporativo</p>
                <h1 class="mt-3 max-w-xl text-3xl font-bold leading-tight xl:text-4xl">Gestão segura, integrada e eficiente.</h1>
                <p class="mt-3 max-w-lg text-sm leading-6 text-blue-100/80">Ambiente interno para gestão comercial e operacional da Argosvig.</p>
            </div>
        </section>

        <main class="flex min-h-screen items-center justify-center px-6 py-10 sm:px-10 lg:bg-white lg:px-12 dark:lg:bg-gray-950">
            <div class="w-full max-w-[420px]">
                <div class="mb-8 flex justify-center lg:justify-start">
                    @if ($logo = core()->getConfigData('general.general.admin_logo.logo_image'))
                        <img class="h-12 max-w-[260px] object-contain" src="{{ Storage::url($logo) }}" alt="Argosvig" />
                    @else
                        <picture>
                            <source media="(min-width: 1024px)" srcset="{{ vite()->asset('images/logo.svg') }}">
                            <img class="h-12 w-auto" src="{{ vite()->asset('images/dark-logo.svg') }}" alt="Argosvig" />
                        </picture>
                    @endif
                </div>

                <div class="rounded-2xl border border-white/10 bg-white p-6 shadow-2xl shadow-black/20 sm:p-8 dark:border-gray-800 dark:bg-gray-900 lg:border-gray-200 lg:shadow-xl">
                    <div class="mb-7">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-brandColor">Acesso restrito</p>
                        <h2 class="mt-2 text-2xl font-bold text-[#061A42] dark:text-white">Bem-vindo à Argosvig</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Entre com suas credenciais corporativas.</p>
                    </div>

                    {!! view_render_event('admin.sessions.login.form_controls.before') !!}

                    <x-admin::form :action="route('admin.session.store')">
                        <div class="space-y-5">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.users.login.email')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="email"
                                    class="w-full"
                                    id="email"
                                    name="email"
                                    rules="required|email"
                                    :label="trans('admin::app.users.login.email')"
                                    :placeholder="trans('admin::app.users.login.email')"
                                />

                                <x-admin::form.control-group.error control-name="email" />
                            </x-admin::form.control-group>

                            <x-admin::form.control-group class="relative w-full">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.users.login.password')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="password"
                                    class="w-full ltr:pr-10 rtl:pl-10"
                                    id="password"
                                    name="password"
                                    rules="required|min:6"
                                    :label="trans('admin::app.users.login.password')"
                                    :placeholder="trans('admin::app.users.login.password')"
                                />

                                <button
                                    type="button"
                                    class="icon-eye-hide absolute top-11 -translate-y-2/4 cursor-pointer border-0 bg-transparent text-2xl ltr:right-3 rtl:left-3"
                                    onclick="switchVisibility()"
                                    id="visibilityIcon"
                                    aria-label="Mostrar ou ocultar senha"
                                ></button>

                                <x-admin::form.control-group.error control-name="password" />
                            </x-admin::form.control-group>
                        </div>

                        <div class="mt-7 flex items-center justify-between gap-4">
                            <a class="text-xs font-semibold leading-6 text-brandColor hover:underline" href="{{ route('admin.forgot_password.create') }}">
                                @lang('admin::app.users.login.forget-password-link')
                            </a>

                            <button class="primary-button min-w-[110px]" aria-label="{{ trans('admin::app.users.login.submit-btn') }}">
                                @lang('admin::app.users.login.submit-btn')
                            </button>
                        </div>
                    </x-admin::form>

                    {!! view_render_event('admin.sessions.login.form_controls.after') !!}
                </div>

                <p class="mt-6 text-center text-xs text-blue-100/70 lg:text-gray-400">
                    © {{ date('Y') }} Argosvig · Ambiente interno e confidencial
                </p>
            </div>
        </main>
    </div>

    @push('scripts')
        <script>
            function switchVisibility() {
                const passwordField = document.getElementById('password');
                const visibilityIcon = document.getElementById('visibilityIcon');

                passwordField.type = passwordField.type === 'password' ? 'text' : 'password';
                visibilityIcon.classList.toggle('icon-eye');
            }
        </script>
    @endpush
</x-admin::layouts.anonymous>
