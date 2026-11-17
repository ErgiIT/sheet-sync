<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportConfigurationRequest;
use App\Services\Configuration\ConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use JsonException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConfigurationController extends Controller
{
    public function index(ConfigurationService $configuration): View
    {
        return view('configuration.index', [
            'json' => json_encode($configuration->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function export(ConfigurationService $configuration): StreamedResponse
    {
        $json = json_encode($configuration->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(fn () => print ($json), 'sheet-sync-' . now()->format('Y-m-d') . '.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    public function import(ImportConfigurationRequest $request, ConfigurationService $configuration): RedirectResponse
    {
        try {
            $document = json_decode($request->file('file')->get(), true, flags: JSON_THROW_ON_ERROR);
            $summary = $configuration->import(is_array($document) ? $document : []);
        } catch (JsonException $e) {
            return back()->withErrors(['file' => "Invalid JSON: {$e->getMessage()}"]);
        } catch (ValidationException $e) {
            return back()->withErrors(['file' => collect($e->errors())->map(fn ($messages, $field) => "{$field}: " . implode(' ', $messages))->implode("\n")]);
        }

        return redirect()->route('configuration.index')->with(
            'status',
            "Imported {$summary['spreadsheets']} spreadsheet(s), {$summary['tabs']} tab(s) and {$summary['mappings']} mapping(s).",
        );
    }
}
