<?php

namespace Tests\Feature;

use App\Livewire\Admin\LocationsIndex;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class LocationCsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('locations.csv', $content);
    }

    public function test_valid_rows_are_imported(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $file = $this->csv("code,name,campus,building\nB12,B Block Room 12,Main Campus,B Block\nC05,C Block Room 05,Main Campus,C Block\n");

        Livewire::actingAs($admin)
            ->test(LocationsIndex::class)
            ->set('csvFile', $file)
            ->call('importCsv');

        $this->assertDatabaseHas('locations', ['code' => 'B12', 'name' => 'B Block Room 12', 'campus' => 'Main Campus']);
        $this->assertDatabaseHas('locations', ['code' => 'C05', 'name' => 'C Block Room 05']);
    }

    public function test_rows_missing_required_columns_are_skipped_not_fatal(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $file = $this->csv("code,name\nB12,B Block Room 12\n,Missing Code\nC05,\n");

        $component = Livewire::actingAs($admin)
            ->test(LocationsIndex::class)
            ->set('csvFile', $file)
            ->call('importCsv');

        $this->assertDatabaseHas('locations', ['code' => 'B12']);
        $this->assertSame(1, $component->get('importResults')['created']);
        $this->assertCount(2, $component->get('importResults')['skipped']);
    }

    public function test_reimporting_the_same_code_updates_rather_than_duplicates(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        Location::factory()->create(['code' => 'B12', 'name' => 'Old Name']);

        $file = $this->csv("code,name\nB12,New Name\n");

        Livewire::actingAs($admin)
            ->test(LocationsIndex::class)
            ->set('csvFile', $file)
            ->call('importCsv');

        $this->assertSame(1, Location::where('code', 'B12')->count());
        $this->assertDatabaseHas('locations', ['code' => 'B12', 'name' => 'New Name']);
    }

    public function test_a_header_without_code_or_name_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $file = $this->csv("building,campus\nB Block,Main\n");

        Livewire::actingAs($admin)
            ->test(LocationsIndex::class)
            ->set('csvFile', $file)
            ->call('importCsv')
            ->assertHasErrors('csvFile');

        $this->assertSame(0, Location::count());
    }
}
