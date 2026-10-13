<?php

namespace App\Http\Controllers;

use App\Models\M16Topic;
use App\Services\TopicMonitoring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class TopicMonitoringController extends Controller
{
    public function index(Request $request, TopicMonitoring $monitor)
    {
        return $this->page(['topics' => M16Topic::where('user_id', $request->user()->id)
            ->with(['versions', 'findings'])->latest()->get(), 'ready' => $monitor->ready()]);
    }

    public function store(Request $request, TopicMonitoring $monitor)
    {
        $validated = $this->input($request);
        if ($validated['errors']) {
            return $this->page(['inputErrors' => $validated['errors'], 'form' => $request->only('title', 'summary')], 422);
        }
        try {
            $topic = $monitor->create($request->user()->id, $validated['data']['title'], $validated['data']['summary']);
        } catch (RuntimeException $exception) {
            return $this->page(['failure' => $exception->getMessage(), 'form' => $validated['data']], 503);
        }

        return redirect()->route('monitor.show', $topic);
    }

    public function show(Request $request, M16Topic $topic, TopicMonitoring $monitor)
    {
        $this->owned($request, $topic);
        $versions = $topic->versions()->orderBy('number')->get();
        $current = $versions->last();
        $compareOlder = $versions->count() > 1 ? $versions->get($versions->count() - 2) : null;
        $compareNewer = $compareOlder ? $current : null;
        if ($request->hasAny(['older', 'newer'])) {
            $olderNumber = $request->query('older');
            $newerNumber = $request->query('newer');
            abort_unless(is_string($olderNumber) && ctype_digit($olderNumber)
                && is_string($newerNumber) && ctype_digit($newerNumber)
                && (int) $olderNumber < (int) $newerNumber, 404);
            $compareOlder = $versions->firstWhere('number', (int) $olderNumber);
            $compareNewer = $versions->firstWhere('number', (int) $newerNumber);
            abort_unless($compareOlder && $compareNewer, 404);
        }

        return $this->page(['topic' => $topic, 'versions' => $versions, 'current' => $current,
            'compareOlder' => $compareOlder, 'compareNewer' => $compareNewer,
            'comparison' => $compareOlder ? $monitor->compare($compareOlder, $compareNewer) : null,
            'findings' => $topic->findings()->latest('seen_at')->get(), 'ready' => $monitor->ready()], 200, 'monitor.show');
    }

    public function revise(Request $request, M16Topic $topic, TopicMonitoring $monitor)
    {
        $this->owned($request, $topic);
        $validated = $this->input($request);
        if ($validated['errors']) {
            return $this->page(['topic' => $topic, 'inputErrors' => $validated['errors'], 'form' => $request->only('title', 'summary')], 422, 'monitor.revise');
        }
        try {
            $monitor->revise($topic, $validated['data']['title'], $validated['data']['summary']);
        } catch (RuntimeException $exception) {
            return $this->page(['topic' => $topic, 'failure' => $exception->getMessage(), 'form' => $validated['data']], 503, 'monitor.revise');
        }

        return redirect()->route('monitor.show', $topic);
    }

    public function edit(Request $request, M16Topic $topic)
    {
        $this->owned($request, $topic);
        $current = $topic->versions()->where('number', $topic->current_version)->firstOrFail();

        return $this->page(['topic' => $topic, 'form' => ['title' => $current->title_cipher, 'summary' => $current->summary_cipher]], 200, 'monitor.revise');
    }

    public function read(Request $request, M16Topic $topic, int $finding)
    {
        $this->owned($request, $topic);
        $topic->findings()->findOrFail($finding)->update(['read_at' => now()]);

        return redirect()->route('monitor.show', $topic);
    }

    public function destroy(Request $request, M16Topic $topic)
    {
        $this->owned($request, $topic);
        $topic->delete();

        return redirect()->route('monitor.index');
    }

    private function owned(Request $request, M16Topic $topic): void
    {
        abort_unless($topic->user_id === $request->user()->id, 404);
    }

    private function input(Request $request): array
    {
        $validator = Validator::make($request->only('title', 'summary', 'consent'), [
            'title' => ['required', 'string', 'min:10', 'max:300'],
            'summary' => ['required', 'string', 'min:80', 'max:1200'],
            'consent' => ['accepted'],
        ]);

        return ['data' => $validator->passes() ? $validator->validated() : null,
            'errors' => $validator->fails() ? $validator->errors()->all() : []];
    }

    private function page(array $data, int $status = 200, string $view = 'monitor.index')
    {
        if ($view === 'monitor.index') {
            $data += ['topics' => M16Topic::where('user_id', auth()->id())->with(['versions', 'findings'])->latest()->get(),
                'ready' => app(TopicMonitoring::class)->ready()];
        }

        return response()->view($view, $data, $status)->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, noarchive');
    }
}
