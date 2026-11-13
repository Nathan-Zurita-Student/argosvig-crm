<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Contact\Models\Organization;

uses(DatabaseTransactions::class);

it('previews company mappings and safely reapplies an import with stable external IDs', function () {
    $existing = Organization::create(['name' => 'Existing migration company']);
    $path = tempnam(sys_get_temp_dir(), 'crm-organizations-');
    $source = fake()->unique()->lexify('old_????????');
    $options = ['file' => $path, '--source' => $source, '--owner' => getDefaultAdmin()->id];
    $name = 'Migration company '.fake()->uuid();

    try {
        $csv = "external_id;name;existing_organization_id\n0001;{$name};\n0002;Existing company;{$existing->id}\n";
        file_put_contents($path, $csv."0003;Existing migration company;\n");
        $this->artisan('crm:import-organizations', $options + ['--apply' => true])->assertFailed();
        $this->assertDatabaseMissing('organizations', ['name' => $name]);

        file_put_contents($path, $csv);
        $this->artisan('crm:import-organizations', $options)->assertSuccessful();
        $this->assertDatabaseMissing('legacy_organization_links', ['source' => $source]);
        $this->artisan('crm:import-organizations', $options + ['--apply' => true])->assertSuccessful();
        $this->artisan('crm:import-organizations', $options + ['--apply' => true])->assertSuccessful();

        expect(Organization::where('name', $name)->count())->toBe(1);
        $this->assertDatabaseHas('legacy_organization_links', ['source' => $source, 'external_id' => '0002', 'organization_id' => $existing->id]);
    } finally {
        unlink($path);
    }
});
