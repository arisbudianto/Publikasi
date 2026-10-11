<?php

namespace Database\Seeders;

use App\Models\Journal;
use App\Services\Catalog;
use Illuminate\Database\Seeder;

class PilotJournalSeeder extends Seeder
{
    public function run(): void
    {
        foreach (json_decode(file_get_contents(database_path('data/pilot-journals.json')), true) as $row) {
            if (! Journal::where('issn', $row['issn'])->exists()) {
                app(Catalog::class)->save($row);
            }
        }
    }
}
