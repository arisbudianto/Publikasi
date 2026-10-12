<?php

namespace App\Http\Controllers;

use App\Services\StructuredObservation;
use App\Services\StructuredObservationImport;
use Illuminate\Http\Request;

class StructuredObservationImportController extends Controller
{
    public function form(Request $request)
    {
        $preview = $request->session()->get('structured_observation_import_preview');
        if (! $preview || $preview['expires'] < time()) {
            $request->session()->forget('structured_observation_import_preview');
            $preview = null;
        }
        if ($preview) {
            $preview['errors'] = [];
        }

        return response()->view('admin.import-observations', compact('preview'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function preview(Request $request, StructuredObservationImport $importer)
    {
        $request->session()->forget('structured_observation_import_preview');
        $request->validate(['csv' => 'required|file|mimes:csv,txt|max:1024']);
        $preview = $importer->preview($request->file('csv')->getRealPath());
        if (! $preview['errors']) {
            $request->session()->put('structured_observation_import_preview', [
                'rows' => $preview['rows'], 'expires' => now()->addMinutes(15)->timestamp,
            ]);
        }

        return response()->view('admin.import-observations', ['preview' => $preview + ['expires' => null]])
            ->header('Cache-Control', 'no-store, private');
    }

    public function commit(Request $request, StructuredObservationImport $importer)
    {
        $preview = $request->session()->get('structured_observation_import_preview');
        abort_unless($preview && $preview['expires'] >= time(), 422, 'Preview kedaluwarsa. Unggah ulang CSV.');
        abort_unless(count($preview['rows']) > 0 && count($preview['rows']) <= 500, 422);
        $count = $importer->import($preview['rows'], $request->user()->id);
        $request->session()->forget('structured_observation_import_preview');

        return redirect()->route('admin.curation')->with('success', "$count observasi terstruktur berhasil diterapkan.");
    }

    public function template()
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, StructuredObservation::FIELDS, ',', '"', '');
            fclose($handle);
        }, 'template-observasi-terstruktur.csv', ['Content-Type' => 'text/csv']);
    }
}
