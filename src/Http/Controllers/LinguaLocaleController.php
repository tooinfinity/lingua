<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use TooInfinity\Lingua\Lingua;

final class LinguaLocaleController extends Controller
{
    public function __construct(
        private readonly Lingua $lingua
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($this->lingua->supportedLocales())],
        ]);

        $this->lingua->setLocale($validated['locale']);

        return redirect()->back();
    }
}
