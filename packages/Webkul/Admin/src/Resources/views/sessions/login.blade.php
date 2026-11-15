<x-admin::layouts.anonymous>
    <x-slot:title>
        Argosvig | @lang('admin::app.users.login.title')
    </x-slot>

    @push('styles')
        @include('admin::sessions.login-styles')
    @endpush

    <div class="sign-in" data-theme="{{ request()->cookie('dark_mode') ? 'dark' : 'light' }}">
        <aside class="sign-in-hero" aria-label="Argosvig">
            <div class="sign-in-identity">
                @if ($logo = core()->getConfigData('general.general.admin_logo.logo_image'))
                    <img src="{{ Storage::url($logo) }}" alt="Argosvig" />
                @else
                    <x-admin::brand.shield />
                    <span>ARGOSVIG</span>
                @endif
            </div>

            <div class="sign-in-emblem" aria-hidden="true">
                <div class="sign-in-orbit">
                    <span class="sign-in-orbit-tracer"></span>
                </div>
                <x-admin::brand.shield />
            </div>

            <div class="sign-in-story">
                <p>@lang('admin::app.users.login.operations')</p>
                <span>@lang('admin::app.users.login.operations-description')</span>
            </div>
        </aside>

        <main class="sign-in-main">
            <div class="sign-in-content">
                <header class="sign-in-heading">
                    <p class="sign-in-portal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="5" y="11" width="14" height="10" rx="2" />
                            <path d="M8 11V7a4 4 0 0 1 8 0v4m-4 4v2" />
                        </svg>
                        @lang('admin::app.users.login.portal')
                    </p>

                    <h1>@lang('admin::app.users.login.heading')</h1>
                </header>

                {!! view_render_event('admin.sessions.login.form_controls.before') !!}

                <x-admin::form :action="route('admin.session.store')" class="sign-in-form">
                    <x-admin::form.control-group class="sign-in-field">
                        <x-admin::form.control-group.label for="email">
                            @lang('admin::app.users.login.email')
                        </x-admin::form.control-group.label>

                        <div class="sign-in-control">
                            <svg class="sign-in-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="3" />
                                <path d="m4 8 8 5 8-5" />
                            </svg>

                            <x-admin::form.control-group.control
                                type="email"
                                id="email"
                                name="email"
                                class="sign-in-input"
                                rules="required|email"
                                :validate-on-submit-only="true"
                                enterkeyhint="next"
                                aria-describedby="email-error"
                                autocomplete="username"
                                autocapitalize="none"
                                spellcheck="false"
                                :label="trans('admin::app.users.login.email')"
                                :placeholder="trans('admin::app.users.login.email')"
                                :value="old('email')"
                            />
                        </div>

                        <x-admin::form.control-group.error control-name="email" id="email-error" role="alert" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group class="sign-in-field">
                        <div class="sign-in-password-label">
                            <x-admin::form.control-group.label for="password">
                                @lang('admin::app.users.login.password')
                            </x-admin::form.control-group.label>

                            <a href="{{ route('admin.forgot_password.create') }}">
                                @lang('admin::app.users.login.forget-password-link')
                            </a>
                        </div>

                        <div class="sign-in-control">
                            <svg class="sign-in-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="5" y="11" width="14" height="10" rx="2" />
                                <path d="M8 11V7a4 4 0 0 1 8 0v4m-4 4v2" />
                            </svg>

                            <x-admin::form.control-group.control
                                type="password"
                                id="password"
                                name="password"
                                class="sign-in-input sign-in-password"
                                rules="required|min:6"
                                :validate-on-submit-only="true"
                                enterkeyhint="go"
                                aria-describedby="password-error"
                                autocomplete="current-password"
                                :label="trans('admin::app.users.login.password')"
                                :placeholder="trans('admin::app.users.login.password')"
                            />

                            <button
                                type="button"
                                id="visibilityIcon"
                                class="sign-in-visibility"
                                aria-controls="password"
                                aria-label="{{ trans('admin::app.users.login.show-password') }}"
                                aria-pressed="false"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2.5 12S6 6 12 6s9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                                    <circle cx="12" cy="12" r="2.5" />
                                    <path class="sign-in-visibility-slash" d="m4 20 16-16" />
                                </svg>
                            </button>
                        </div>

                        <x-admin::form.control-group.error control-name="password" id="password-error" role="alert" />
                    </x-admin::form.control-group>

                    <button type="submit" class="sign-in-submit">
                        @lang('admin::app.users.login.submit-btn')

                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 12h15m-6-6 6 6-6 6" />
                        </svg>
                    </button>
                </x-admin::form>

                {!! view_render_event('admin.sessions.login.form_controls.after') !!}
            </div>
        </main>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' || event.isComposing || ! event.target.matches('.sign-in-form #email')) {
                    return;
                }

                event.preventDefault();
                event.target.form.elements.namedItem('password').focus();
            });

            document.addEventListener('click', (event) => {
                const toggle = event.target.closest('.sign-in-visibility');

                if (! toggle) {
                    return;
                }

                const password = document.getElementById(toggle.getAttribute('aria-controls'));
                const visible = password.type === 'password';

                password.type = visible ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', String(visible));
            });
        </script>
    @endpush
</x-admin::layouts.anonymous>
