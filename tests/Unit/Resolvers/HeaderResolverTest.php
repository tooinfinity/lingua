<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TooInfinity\Lingua\Support\Resolvers\HeaderResolver;

beforeEach(function (): void {
    $this->resolver = app(HeaderResolver::class);
});

describe('HeaderResolver', function (): void {
    describe('with quality values enabled', function (): void {
        beforeEach(function (): void {
            config(['lingua.resolvers.header.use_quality' => true]);
            $this->resolver = app(HeaderResolver::class);
        });

        it('returns highest priority locale from Accept-Language header', function (): void {
            $request = Request::create('/');
            $request->headers->set('Accept-Language', 'en-US,en;q=0.9,fr;q=0.8');

            expect($this->resolver->resolve($request))->toBe('en-US');
        });

        it('respects quality values and returns highest quality locale', function (): void {
            $request = Request::create('/');
            $request->headers->set('Accept-Language', 'en;q=0.5,fr;q=0.9,de;q=0.7');

            expect($this->resolver->resolve($request))->toBe('fr');
        });

        it('handles single locale without quality', function (): void {
            $request = Request::create('/');
            $request->headers->set('Accept-Language', 'fr');

            expect($this->resolver->resolve($request))->toBe('fr');
        });

        it('defaults to quality 1.0 when not specified', function (): void {
            $request = Request::create('/');
            $request->headers->set('Accept-Language', 'fr,en;q=0.9');

            expect($this->resolver->resolve($request))->toBe('fr');
        });

        it('handles complex Accept-Language header', function (): void {
            $request = Request::create('/');
            $request->headers->set('Accept-Language', 'en-GB;q=0.8,en-US;q=0.9,en;q=0.7,fr;q=0.6');

            expect($this->resolver->resolve($request))->toBe('en-US');
        });

        it('clamps quality values to valid range', function (): void {
            $request = Request::create('/');
            $request->headers->set('Accept-Language', 'en;q=1.5,fr;q=0.5');

            // q=1.5 should be clamped to 1.0
            expect($this->resolver->resolve($request))->toBe('en');
        });

        it('handles negative quality values', function (): void {
            $request = Request::create('/');
            $request->headers->set('Accept-Language', 'en;q=-0.5,fr;q=0.5');

            // q=-0.5 should be clamped to 0.0, so fr wins
            expect($this->resolver->resolve($request))->toBe('fr');
        });
    });

    describe('with quality values disabled', function (): void {
        beforeEach(function (): void {
            config(['lingua.resolvers.header.use_quality' => false]);
            $this->resolver = app(HeaderResolver::class);
        });

        it('returns first locale ignoring quality values', function (): void {
            $request = Request::create('/');
            $request->headers->set('Accept-Language', 'en;q=0.5,fr;q=0.9');

            expect($this->resolver->resolve($request))->toBe('en');
        });

        it('handles locale with quality in first position', function (): void {
            $request = Request::create('/');
            $request->headers->set('Accept-Language', 'de;q=0.3,fr,en');

            expect($this->resolver->resolve($request))->toBe('de');
        });
    });

    describe('edge cases', function (): void {
        it('returns null when header is not set', function (): void {
            $request = Request::create('/');
            $request->headers->remove('Accept-Language');

            expect($this->resolver->resolve($request))->toBeNull();
        });

        it('returns null when header is empty string', function (): void {
            $request = Request::create('/');
            $request->headers->set('Accept-Language', '');

            expect($this->resolver->resolve($request))->toBeNull();
        });

        it('handles whitespace in header', function (): void {
            $request = Request::create('/');
            $request->headers->set('Accept-Language', '  fr  ,  en ; q=0.9  ');

            expect($this->resolver->resolve($request))->toBe('fr');
        });

        it('handles malformed quality values gracefully', function (): void {
            $request = Request::create('/');
            $request->headers->set('Accept-Language', 'en;q=invalid,fr;q=0.5');

            // 'invalid' parses to 0.0, so fr wins
            expect($this->resolver->resolve($request))->toBe('fr');
        });

        it('handles header with only commas', function (): void {
            $request = Request::create('/');
            $request->headers->set('Accept-Language', ',,,');

            expect($this->resolver->resolve($request))->toBeNull();
        });

        it('handles wildcard locale', function (): void {
            $request = Request::create('/');
            $request->headers->set('Accept-Language', '*');

            expect($this->resolver->resolve($request))->toBe('*');
        });

        it('handles header with empty locale after semicolon in quality mode', function (): void {
            config(['lingua.resolvers.header.use_quality' => true]);
            $resolver = app(HeaderResolver::class);

            $request = Request::create('/');
            // Empty locale before semicolon should be skipped
            $request->headers->set('Accept-Language', ';q=0.9,fr;q=0.8');

            expect($resolver->resolve($request))->toBe('fr');
        });

        it('handles header with only semicolons and quality in simple mode', function (): void {
            config(['lingua.resolvers.header.use_quality' => false]);
            $resolver = app(HeaderResolver::class);

            $request = Request::create('/');
            // Empty locale after extracting part before semicolon
            $request->headers->set('Accept-Language', ';q=0.9,fr');

            expect($resolver->resolve($request))->toBe('fr');
        });

        it('handles header with spaces only between commas in simple mode', function (): void {
            config(['lingua.resolvers.header.use_quality' => false]);
            $resolver = app(HeaderResolver::class);

            $request = Request::create('/');
            $request->headers->set('Accept-Language', '  ,  , fr');

            expect($resolver->resolve($request))->toBe('fr');
        });
    });
});
