<?php

namespace App\Http\Controllers;

use App\Services\AuthorshipEvaluator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthorshipController extends Controller
{
    public function index(AuthorshipEvaluator $evaluator)
    {
        return response()->view('authorship.index', [
            'criteria' => $evaluator->criteria(),
            'roles' => $evaluator->roles(),
            'people' => [['name' => '', 'criteria' => [], 'roles' => []]],
            'result' => null,
            'inputErrors' => null,
        ])->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, noarchive');
    }

    public function analyze(Request $request, AuthorshipEvaluator $evaluator)
    {
        $input = $request->only('people');
        if (is_array($input['people'] ?? null)) {
            foreach ($input['people'] as $index => $person) {
                if (! is_array($person)) {
                    continue;
                }
                $input['people'][$index]['criteria'] = is_array($person['criteria'] ?? null) ? $person['criteria'] : [];
                $input['people'][$index]['roles'] = is_array($person['roles'] ?? null) ? $person['roles'] : [];
            }
        }

        $validator = Validator::make($input, [
            'people' => ['required', 'array', 'min:1', 'max:30'],
            'people.*' => ['required', 'array:name,criteria,roles'],
            'people.*.name' => ['required', 'string', 'max:100'],
            'people.*.criteria' => ['present', 'array', 'max:4'],
            'people.*.criteria.*' => ['required', 'distinct', Rule::in(array_keys($evaluator->criteria()))],
            'people.*.roles' => ['present', 'array', 'max:14'],
            'people.*.roles.*' => ['required', 'distinct', Rule::in(array_keys($evaluator->roles()))],
        ]);

        if ($validator->fails()) {
            $displayPeople = array_values(array_filter(
                is_array($input['people'] ?? null) ? $input['people'] : [],
                fn ($person) => is_array($person),
            ));

            return response()->view('authorship.index', [
                'criteria' => $evaluator->criteria(),
                'roles' => $evaluator->roles(),
                'people' => $displayPeople !== [] ? $displayPeople : [['name' => '', 'criteria' => [], 'roles' => []]],
                'result' => null,
                'inputErrors' => $validator->errors(),
            ], 422)->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, noarchive');
        }

        $data = $validator->validated();
        $result = $evaluator->evaluate($data['people']);

        return response()->view('authorship.index', [
            'criteria' => $evaluator->criteria(),
            'roles' => $evaluator->roles(),
            'people' => $data['people'],
            'result' => $result,
            'inputErrors' => null,
        ])->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, noarchive');
    }
}
