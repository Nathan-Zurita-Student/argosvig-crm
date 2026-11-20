<?php

use Webkul\Core\Services\CrmAi;

return [
    /**
     * General.
     */
    [
        'key' => 'general',
        'name' => 'admin::app.configuration.index.general.title',
        'info' => 'admin::app.configuration.index.general.info',
        'sort' => 1,
    ], [
        'key' => 'general.general',
        'name' => 'admin::app.configuration.index.general.general.title',
        'info' => 'admin::app.configuration.index.general.general.info',
        'icon' => 'icon-setting',
        'sort' => 1,
    ], [
        'key' => 'general.general.locale_settings',
        'name' => 'admin::app.configuration.index.general.general.locale-settings.title',
        'info' => 'admin::app.configuration.index.general.general.locale-settings.title-info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'locale',
                'title' => 'admin::app.configuration.index.general.general.locale-settings.title',
                'type' => 'select',
                'default' => 'en',
                'options' => 'Webkul\Core\Core@locales',
            ],
        ],
    ], [
        'key' => 'general.general.admin_logo',
        'name' => 'admin::app.configuration.index.general.general.admin-logo.title',
        'info' => 'admin::app.configuration.index.general.general.admin-logo.title-info',
        'sort' => 2,
        'fields' => [
            [
                'name' => 'logo_image',
                'title' => 'admin::app.configuration.index.general.general.admin-logo.logo-image',
                'type' => 'image',
                'validation' => 'mimes:bmp,jpeg,jpg,png,webp',
            ], [
                'name' => 'favicon_image',
                'title' => 'admin::app.configuration.index.general.general.admin-logo.favicon-image',
                'type' => 'image',
                'validation' => 'mimes:ico,jpeg,jpg,png,webp',
            ],
        ],
    ], [
        'key' => 'general.settings',
        'name' => 'admin::app.configuration.index.general.settings.title',
        'info' => 'admin::app.configuration.index.general.settings.info',
        'icon' => 'icon-configuration',
        'sort' => 2,
    ], [
        'key' => 'general.settings.footer',
        'name' => 'admin::app.configuration.index.general.settings.footer.title',
        'info' => 'admin::app.configuration.index.general.settings.footer.info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'show',
                'title' => 'admin::app.configuration.index.general.settings.footer.show',
                'info' => 'admin::app.configuration.index.general.settings.footer.show-info',
                'type' => 'boolean',
                'default' => 1,
            ], [
                'name' => 'label',
                'title' => 'admin::app.configuration.index.general.settings.footer.powered-by',
                'type' => 'editor',
                'depends' => 'show:1',
                'default' => '© Argosvig · Ambiente interno e confidencial.',
                'tinymce' => true,
            ],
        ],
    ], [
        'key' => 'general.settings.menu',
        'name' => 'admin::app.configuration.index.general.settings.menu.title',
        'info' => 'admin::app.configuration.index.general.settings.menu.info',
        'sort' => 2,
        'fields' => [
            [
                'name' => 'dashboard',
                'title' => 'admin::app.configuration.index.general.settings.menu.dashboard',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'leads',
                'title' => 'admin::app.configuration.index.general.settings.menu.leads',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'quotes',
                'title' => 'admin::app.configuration.index.general.settings.menu.quotes',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'mail',
                'title' => 'admin::app.configuration.index.general.settings.menu.mail',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'mail.inbox',
                'title' => 'admin::app.configuration.index.general.settings.menu.inbox',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'mail.draft',
                'title' => 'admin::app.configuration.index.general.settings.menu.draft',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'mail.outbox',
                'title' => 'admin::app.configuration.index.general.settings.menu.outbox',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'mail.sent',
                'title' => 'admin::app.configuration.index.general.settings.menu.sent',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'mail.trash',
                'title' => 'admin::app.configuration.index.general.settings.menu.trash',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'activities',
                'title' => 'admin::app.configuration.index.general.settings.menu.activities',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'contacts',
                'title' => 'admin::app.configuration.index.general.settings.menu.contacts',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'contacts.persons',
                'title' => 'admin::app.configuration.index.general.settings.menu.persons',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'contacts.organizations',
                'title' => 'admin::app.configuration.index.general.settings.menu.organizations',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'products',
                'title' => 'admin::app.configuration.index.general.settings.menu.products',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'settings',
                'title' => 'admin::app.configuration.index.general.settings.menu.settings',
                'type' => 'text',
                'validation' => 'max:20',
            ], [
                'name' => 'configuration',
                'title' => 'admin::app.configuration.index.general.settings.menu.configuration',
                'type' => 'text',
                'validation' => 'max:20',
            ],
        ],
    ], [
        'key' => 'general.settings.menu_color',
        'name' => 'admin::app.configuration.index.general.settings.menu-color.title',
        'info' => 'admin::app.configuration.index.general.settings.menu-color.info',
        'sort' => 3,
        'fields' => [
            [
                'name' => 'brand_color',
                'title' => 'admin::app.configuration.index.general.settings.menu-color.brand-color',
                'type' => 'color',
                'default' => '#244A96',
            ],
        ],
    ], [
        'key' => 'general.settings.dashboard',
        'name' => 'admin::app.configuration.index.general.settings.dashboard.title',
        'info' => 'admin::app.configuration.index.general.settings.dashboard.info',
        'sort' => 4,
        'fields' => [
            [
                'name' => 'date_range',
                'title' => 'admin::app.configuration.index.general.settings.dashboard.date-range',
                'info' => 'admin::app.configuration.index.general.settings.dashboard.date-range-info',
                'type' => 'select',
                'default' => '1_month',
                'options' => [
                    [
                        'title' => 'admin::app.configuration.index.general.settings.dashboard.ranges.1-month',
                        'value' => '1_month',
                    ], [
                        'title' => 'admin::app.configuration.index.general.settings.dashboard.ranges.3-months',
                        'value' => '3_months',
                    ], [
                        'title' => 'admin::app.configuration.index.general.settings.dashboard.ranges.9-months',
                        'value' => '9_months',
                    ], [
                        'title' => 'admin::app.configuration.index.general.settings.dashboard.ranges.1-year',
                        'value' => '1_year',
                    ], [
                        'title' => 'admin::app.configuration.index.general.settings.dashboard.ranges.2-years',
                        'value' => '2_years',
                    ], [
                        'title' => 'admin::app.configuration.index.general.settings.dashboard.ranges.custom',
                        'value' => 'custom',
                    ],
                ],
            ], [
                'name' => 'custom_days',
                'title' => 'admin::app.configuration.index.general.settings.dashboard.custom-days',
                'info' => 'admin::app.configuration.index.general.settings.dashboard.custom-days-info',
                'type' => 'number',
                'default' => 30,
                'depends' => 'date_range:custom',
                'validation' => 'required_if:date_range,custom',
            ],
        ],
    ], [
        'key' => 'general.settings.notifications',
        'name' => 'admin::app.configuration.index.general.settings.notifications.title',
        'info' => 'admin::app.configuration.index.general.settings.notifications.info',
        'sort' => 5,
        'fields' => [
            [
                'name' => 'enabled',
                'title' => 'admin::app.configuration.index.general.settings.notifications.enabled',
                'info' => 'admin::app.configuration.index.general.settings.notifications.enabled-info',
                'type' => 'boolean',
                'default' => 1,
            ], [
                'name' => 'sound',
                'title' => 'admin::app.configuration.index.general.settings.notifications.sound',
                'info' => 'admin::app.configuration.index.general.settings.notifications.sound-info',
                'type' => 'boolean',
                'default' => 0,
            ],
        ],
    ], [
        'key' => 'general.crm_ai',
        'name' => 'human_resources::ai.settings_title',
        'info' => 'human_resources::ai.settings_intro',
        'icon' => 'icon-setting',
        'sort' => 3,
    ], [
        'key' => 'general.crm_ai.settings',
        'name' => 'human_resources::ai.settings_title',
        'info' => 'human_resources::ai.settings_intro',
        'sort' => 1,
        /**
         * The whole section is drawn by one guided panel (provider, key, model and
         * transcription). The remaining fields are declared so the values are saved and
         * found by the configuration search; their own view renders nothing.
         */
        'fields' => [
            [
                'name' => 'enable',
                'title' => 'human_resources::ai.enabled',
                'type' => 'blade',
                'path' => 'human_resources::ai.settings',
            ], [
                'name' => 'provider',
                'title' => 'human_resources::ai.provider',
                'type' => 'blade',
                'path' => 'human_resources::ai.settings-stored',
            ], [
                'name' => 'audio_provider',
                'title' => 'human_resources::ai.audio_provider',
                'type' => 'blade',
                'path' => 'human_resources::ai.settings-stored',
            ],
            ...array_merge(...array_map(
                fn ($provider, $model) => [
                    [
                        'name' => $provider.'_key',
                        'title' => 'human_resources::ai.credentials.'.$provider,
                        'type' => 'blade',
                        'path' => 'human_resources::ai.settings-stored',
                        'validation' => 'max:255',
                    ], [
                        'name' => $provider.'_model',
                        'title' => 'human_resources::ai.models.'.$provider,
                        'type' => 'blade',
                        'path' => 'human_resources::ai.settings-stored',
                        'default' => $model,
                    ],
                    ...isset(CrmAi::AUDIO_MODELS[$provider]) ? [[
                        'name' => $provider.'_audio_model',
                        'title' => 'human_resources::ai.audio_models.'.$provider,
                        'type' => 'blade',
                        'path' => 'human_resources::ai.settings-stored',
                        'default' => CrmAi::AUDIO_MODELS[$provider],
                    ]] : [],
                ],
                array_keys(CrmAi::MODELS),
                array_values(CrmAi::MODELS),
            )),
        ],
    ], [
        'key' => 'general.crm_ai.documents',
        'name' => 'human_resources::ai.document_import',
        'info' => 'human_resources::ai.document_import_help',
        'sort' => 2,
        'fields' => [
            [
                'name' => 'enabled',
                'title' => 'human_resources::ai.document_import_enabled',
                'type' => 'boolean',
            ],
        ],
    ],

    /**
     * Email.
     */
    [
        'key' => 'email',
        'name' => 'admin::app.configuration.index.email.title',
        'info' => 'admin::app.configuration.index.email.info',
        'sort' => 2,
    ], [
        'key' => 'email.imap',
        'name' => 'admin::app.configuration.index.email.imap.title',
        'info' => 'admin::app.configuration.index.email.imap.info',
        'icon' => 'icon-setting',
        'sort' => 1,
    ], [
        'key' => 'email.imap.account',
        'name' => 'admin::app.configuration.index.email.imap.account.title',
        'info' => 'admin::app.configuration.index.email.imap.account.title-info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'host',
                'title' => 'admin::app.configuration.index.email.imap.account.host',
                'type' => 'text',
                'default' => config('imap.accounts.default.host'),
            ],
            [
                'name' => 'port',
                'title' => 'admin::app.configuration.index.email.imap.account.port',
                'type' => 'text',
                'default' => config('imap.accounts.default.port'),
            ],
            [
                'name' => 'encryption',
                'title' => 'admin::app.configuration.index.email.imap.account.encryption',
                'type' => 'text',
                'default' => config('imap.accounts.default.encryption'),
            ],
            [
                'name' => 'validate_cert',
                'title' => 'admin::app.configuration.index.email.imap.account.validate-cert',
                'type' => 'boolean',
                'default' => config('imap.accounts.default.validate_cert'),
            ],
            [
                'name' => 'username',
                'title' => 'admin::app.configuration.index.email.imap.account.username',
                'type' => 'text',
                'default' => config('imap.accounts.default.username'),
            ],
            [
                'name' => 'password',
                'title' => 'admin::app.configuration.index.email.imap.account.password',
                'type' => 'password',
                'default' => config('imap.accounts.default.password'),
            ],
        ],
    ],
];
