<?php

namespace Webkul\Admin\Helpers;

class CommercialLabel
{
    private const DEFAULTS = [
        'pipeline' => ['Default Pipeline' => 'default'],
        'pipeline.pipeline-stages' => [
            'New' => 'new',
            'Follow Up' => 'follow-up',
            'Prospect' => 'prospect',
            'Negotiation' => 'negotiation',
            'Won' => 'won',
            'Lost' => 'lost',
        ],
        'type' => ['New Business' => 'new-business', 'Existing Business' => 'existing-business'],
        'source' => [
            'Direct' => 'direct',
            'Email' => 'email',
            'Phone' => 'phone',
            'Web' => 'web',
            'Web Form' => 'web-form',
        ],
    ];

    public static function display(?string $name, string $group): ?string
    {
        $suffix = self::DEFAULTS[$group][$name] ?? null;

        if ($suffix === null) {
            return $name;
        }

        $key = "installer::app.seeders.lead.{$group}.{$suffix}";

        return trans()->has($key) ? trans($key) : $name;
    }
}
