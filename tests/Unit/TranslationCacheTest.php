<?php

declare(strict_types=1);

use TooInfinity\Lingua\Support\TranslationCache;

beforeEach(function (): void {
    $this->cache = new TranslationCache;
});

describe('has', function (): void {
    it('returns false when cache is empty', function (): void {
        expect($this->cache->has('en', 'common'))->toBeFalse();
    });

    it('returns true when translation group is cached', function (): void {
        $this->cache->put('en', 'common', ['welcome' => 'Welcome']);

        expect($this->cache->has('en', 'common'))->toBeTrue();
    });

    it('returns false for different locale', function (): void {
        $this->cache->put('en', 'common', ['welcome' => 'Welcome']);

        expect($this->cache->has('fr', 'common'))->toBeFalse();
    });

    it('returns false for different group', function (): void {
        $this->cache->put('en', 'common', ['welcome' => 'Welcome']);

        expect($this->cache->has('en', 'auth'))->toBeFalse();
    });
});

describe('get', function (): void {
    it('returns null when cache is empty', function (): void {
        expect($this->cache->get('en', 'common'))->toBeNull();
    });

    it('returns cached translations', function (): void {
        $translations = ['welcome' => 'Welcome', 'goodbye' => 'Goodbye'];
        $this->cache->put('en', 'common', $translations);

        expect($this->cache->get('en', 'common'))->toBe($translations);
    });

    it('returns null for different locale', function (): void {
        $this->cache->put('en', 'common', ['welcome' => 'Welcome']);

        expect($this->cache->get('fr', 'common'))->toBeNull();
    });
});

describe('put', function (): void {
    it('stores translations for locale and group', function (): void {
        $translations = ['key' => 'value'];
        $this->cache->put('en', 'messages', $translations);

        expect($this->cache->get('en', 'messages'))->toBe($translations);
    });

    it('overwrites existing translations', function (): void {
        $this->cache->put('en', 'messages', ['old' => 'value']);
        $this->cache->put('en', 'messages', ['new' => 'value']);

        expect($this->cache->get('en', 'messages'))->toBe(['new' => 'value']);
    });

    it('stores multiple groups for same locale', function (): void {
        $this->cache->put('en', 'auth', ['login' => 'Login']);
        $this->cache->put('en', 'common', ['welcome' => 'Welcome']);

        expect($this->cache->get('en', 'auth'))->toBe(['login' => 'Login']);
        expect($this->cache->get('en', 'common'))->toBe(['welcome' => 'Welcome']);
    });

    it('stores same group for different locales', function (): void {
        $this->cache->put('en', 'common', ['welcome' => 'Welcome']);
        $this->cache->put('fr', 'common', ['welcome' => 'Bienvenue']);

        expect($this->cache->get('en', 'common'))->toBe(['welcome' => 'Welcome']);
        expect($this->cache->get('fr', 'common'))->toBe(['welcome' => 'Bienvenue']);
    });
});

describe('getAllForLocale', function (): void {
    it('returns empty array when no translations cached', function (): void {
        expect($this->cache->getAllForLocale('en'))->toBe([]);
    });

    it('returns all cached groups for locale', function (): void {
        $this->cache->put('en', 'auth', ['login' => 'Login']);
        $this->cache->put('en', 'common', ['welcome' => 'Welcome']);

        $result = $this->cache->getAllForLocale('en');

        expect($result)->toBe([
            'auth' => ['login' => 'Login'],
            'common' => ['welcome' => 'Welcome'],
        ]);
    });

    it('does not return translations from other locales', function (): void {
        $this->cache->put('en', 'common', ['welcome' => 'Welcome']);
        $this->cache->put('fr', 'common', ['welcome' => 'Bienvenue']);

        expect($this->cache->getAllForLocale('en'))->toBe([
            'common' => ['welcome' => 'Welcome'],
        ]);
    });
});

describe('flush', function (): void {
    it('clears all cached translations', function (): void {
        $this->cache->put('en', 'auth', ['login' => 'Login']);
        $this->cache->put('fr', 'common', ['welcome' => 'Bienvenue']);

        $this->cache->flush();

        expect($this->cache->has('en', 'auth'))->toBeFalse();
        expect($this->cache->has('fr', 'common'))->toBeFalse();
    });
});

describe('flushLocale', function (): void {
    it('clears cached translations for specific locale', function (): void {
        $this->cache->put('en', 'auth', ['login' => 'Login']);
        $this->cache->put('en', 'common', ['welcome' => 'Welcome']);
        $this->cache->put('fr', 'common', ['welcome' => 'Bienvenue']);

        $this->cache->flushLocale('en');

        expect($this->cache->has('en', 'auth'))->toBeFalse();
        expect($this->cache->has('en', 'common'))->toBeFalse();
        expect($this->cache->has('fr', 'common'))->toBeTrue();
    });
});

describe('forget', function (): void {
    it('removes specific group from cache', function (): void {
        $this->cache->put('en', 'auth', ['login' => 'Login']);
        $this->cache->put('en', 'common', ['welcome' => 'Welcome']);

        $this->cache->forget('en', 'auth');

        expect($this->cache->has('en', 'auth'))->toBeFalse();
        expect($this->cache->has('en', 'common'))->toBeTrue();
    });

    it('does not affect other locales', function (): void {
        $this->cache->put('en', 'auth', ['login' => 'Login']);
        $this->cache->put('fr', 'auth', ['login' => 'Connexion']);

        $this->cache->forget('en', 'auth');

        expect($this->cache->has('en', 'auth'))->toBeFalse();
        expect($this->cache->has('fr', 'auth'))->toBeTrue();
    });
});
