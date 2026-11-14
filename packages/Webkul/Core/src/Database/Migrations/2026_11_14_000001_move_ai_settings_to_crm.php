<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CODES = [
        'general.magic_ai.settings.enable' => 'general.crm_ai.settings.enable',
        'general.magic_ai.settings.api_key' => 'general.crm_ai.settings.openrouter_key',
        'general.magic_ai.settings.model' => 'general.crm_ai.settings.legacy_model',
        'general.magic_ai.settings.other_model' => 'general.crm_ai.settings.openrouter_model',
        'general.magic_ai.doc_generation.enabled' => 'general.crm_ai.documents.enabled',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::CODES as $old => $new) {
                DB::table('core_config')->where('code', $old)->update(['code' => $new]);
            }

            DB::table('core_config')->where('code', 'general.crm_ai.settings.openrouter_key')
                ->get(['id', 'value'])->each(function ($row) {
                    if (filled($row->value) && ! str_starts_with($row->value, 'encrypted:')) {
                        DB::table('core_config')->where('id', $row->id)
                            ->update(['value' => 'encrypted:'.Crypt::encryptString($row->value)]);
                    }
                });
        });
    }

    public function down(): void
    {
        foreach (array_reverse(self::CODES, true) as $old => $new) {
            DB::table('core_config')->where('code', $new)->update(['code' => $old]);
        }
    }
};
