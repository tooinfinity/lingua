<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Http\Controllers;

use Illuminate\Http\JsonResponse;
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

    /**
     * Update the current locale.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($this->lingua->supportedLocales())],
        ]);

        /** @var string $locale */
        $locale = $validated['locale'];
        $this->lingua->setLocale($locale);

        return redirect()->back();
    }

    /**
     * Get translations for a specific group.
     *
     * This endpoint allows fetching translation groups dynamically via AJAX
     * when lazy loading is enabled.
     */
    public function translations(string $group): JsonResponse
    {
        $translations = $this->lingua->translationGroup($group);

        return response()->json([
            'group' => $group,
            'locale' => $this->lingua->getLocale(),
            'translations' => $translations,
        ]);
    }

    /**
     * Get translations for multiple groups.
     *
     * Accepts groups as comma-separated query parameter or JSON body.
     */
    public function translationsForGroups(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => ['required', 'string'],
        ]);

        /** @var array<string> $groups */
        $groups = $validated['groups'];

        $translations = $this->lingua->translationsFor($groups);

        return response()->json([
            'locale' => $this->lingua->getLocale(),
            'translations' => $translations,
        ]);
    }

    /**
     * Get all available translation groups for the current locale.
     */
    public function availableGroups(): JsonResponse
    {
        return response()->json([
            'locale' => $this->lingua->getLocale(),
            'groups' => $this->lingua->availableGroups(),
        ]);
    }
}
