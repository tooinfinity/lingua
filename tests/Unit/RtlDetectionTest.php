<?php

declare(strict_types=1);

use TooInfinity\Lingua\Lingua;

beforeEach(function (): void {
    $this->lingua = app(Lingua::class);
});

describe('isRtl', function (): void {
    it('returns true for Arabic locale', function (): void {
        expect($this->lingua->isRtl('ar'))->toBeTrue();
    });

    it('returns true for Hebrew locale', function (): void {
        expect($this->lingua->isRtl('he'))->toBeTrue();
    });

    it('returns true for Persian/Farsi locale', function (): void {
        expect($this->lingua->isRtl('fa'))->toBeTrue();
    });

    it('returns true for Urdu locale', function (): void {
        expect($this->lingua->isRtl('ur'))->toBeTrue();
    });

    it('returns true for all default RTL locales', function (): void {
        $rtlLocales = ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'ku', 'ug', 'yi', 'prs', 'dv'];

        foreach ($rtlLocales as $locale) {
            expect($this->lingua->isRtl($locale))->toBeTrue(sprintf('Expected %s to be RTL', $locale));
        }
    });

    it('returns false for English locale', function (): void {
        expect($this->lingua->isRtl('en'))->toBeFalse();
    });

    it('returns false for French locale', function (): void {
        expect($this->lingua->isRtl('fr'))->toBeFalse();
    });

    it('returns false for German locale', function (): void {
        expect($this->lingua->isRtl('de'))->toBeFalse();
    });

    it('returns false for Spanish locale', function (): void {
        expect($this->lingua->isRtl('es'))->toBeFalse();
    });

    it('returns false for common LTR locales', function (): void {
        $ltrLocales = ['en', 'fr', 'de', 'es', 'it', 'pt', 'nl', 'ru', 'zh', 'ja', 'ko'];

        foreach ($ltrLocales as $locale) {
            expect($this->lingua->isRtl($locale))->toBeFalse(sprintf('Expected %s to be LTR', $locale));
        }
    });

    it('uses current locale when no parameter passed', function (): void {
        config(['lingua.locales' => ['en', 'ar']]);

        // Set locale to Arabic
        $this->lingua->setLocale('ar');

        expect($this->lingua->isRtl())->toBeTrue();
    });

    it('uses current locale for LTR when no parameter passed', function (): void {
        config(['lingua.locales' => ['en', 'fr']]);

        // Default locale is English
        expect($this->lingua->isRtl())->toBeFalse();
    });

    it('handles locale variants with region codes', function (): void {
        expect($this->lingua->isRtl('ar_SA'))->toBeTrue();
        expect($this->lingua->isRtl('ar_EG'))->toBeTrue();
        expect($this->lingua->isRtl('he_IL'))->toBeTrue();
        expect($this->lingua->isRtl('fa_IR'))->toBeTrue();
    });

    it('handles hyphenated locale variants', function (): void {
        expect($this->lingua->isRtl('ar-SA'))->toBeTrue();
        expect($this->lingua->isRtl('he-IL'))->toBeTrue();
        expect($this->lingua->isRtl('fa-IR'))->toBeTrue();
    });

    it('handles LTR locale variants with region codes', function (): void {
        expect($this->lingua->isRtl('en_US'))->toBeFalse();
        expect($this->lingua->isRtl('en_GB'))->toBeFalse();
        expect($this->lingua->isRtl('fr_FR'))->toBeFalse();
        expect($this->lingua->isRtl('de_DE'))->toBeFalse();
    });

    it('handles case variations in locale codes', function (): void {
        expect($this->lingua->isRtl('AR'))->toBeTrue();
        expect($this->lingua->isRtl('Ar'))->toBeTrue();
        expect($this->lingua->isRtl('AR_SA'))->toBeTrue();
        expect($this->lingua->isRtl('ar_sa'))->toBeTrue();
    });
});

describe('getRtlLocales', function (): void {
    it('returns configured RTL locales', function (): void {
        $rtlLocales = $this->lingua->getRtlLocales();

        expect($rtlLocales)->toBeArray()
            ->and($rtlLocales)->toContain('ar')
            ->and($rtlLocales)->toContain('he')
            ->and($rtlLocales)->toContain('fa')
            ->and($rtlLocales)->toContain('ur');
    });

    it('returns all default RTL locales', function (): void {
        $expected = ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'ku', 'ug', 'yi', 'prs', 'dv'];

        expect($this->lingua->getRtlLocales())->toBe($expected);
    });

    it('returns custom RTL locales when configured', function (): void {
        config(['lingua.rtl_locales' => ['ar', 'he', 'custom_rtl']]);

        expect($this->lingua->getRtlLocales())->toBe(['ar', 'he', 'custom_rtl']);
    });

    it('returns empty array when configured as empty', function (): void {
        config(['lingua.rtl_locales' => []]);

        expect($this->lingua->getRtlLocales())->toBe([]);
    });

    it('allows adding custom RTL locales via config', function (): void {
        // Use a simple locale code without underscores (underscores indicate region codes)
        config(['lingua.rtl_locales' => ['ar', 'he', 'fa', 'xx']]);

        $rtlLocales = $this->lingua->getRtlLocales();

        expect($rtlLocales)->toContain('xx');
        expect($this->lingua->isRtl('xx'))->toBeTrue();
        // Also verify it works with region variants
        expect($this->lingua->isRtl('xx_YY'))->toBeTrue();
    });
});

describe('getDirection', function (): void {
    it('returns rtl for Arabic locale', function (): void {
        expect($this->lingua->getDirection('ar'))->toBe('rtl');
    });

    it('returns rtl for Hebrew locale', function (): void {
        expect($this->lingua->getDirection('he'))->toBe('rtl');
    });

    it('returns rtl for Persian locale', function (): void {
        expect($this->lingua->getDirection('fa'))->toBe('rtl');
    });

    it('returns ltr for English locale', function (): void {
        expect($this->lingua->getDirection('en'))->toBe('ltr');
    });

    it('returns ltr for French locale', function (): void {
        expect($this->lingua->getDirection('fr'))->toBe('ltr');
    });

    it('returns ltr for German locale', function (): void {
        expect($this->lingua->getDirection('de'))->toBe('ltr');
    });

    it('uses current locale when no parameter passed', function (): void {
        config(['lingua.locales' => ['en', 'ar']]);

        // Default is English (LTR)
        expect($this->lingua->getDirection())->toBe('ltr');

        // Set to Arabic
        $this->lingua->setLocale('ar');
        expect($this->lingua->getDirection())->toBe('rtl');
    });

    it('handles locale variants with region codes', function (): void {
        expect($this->lingua->getDirection('ar_SA'))->toBe('rtl');
        expect($this->lingua->getDirection('en_US'))->toBe('ltr');
        expect($this->lingua->getDirection('he_IL'))->toBe('rtl');
        expect($this->lingua->getDirection('fr_FR'))->toBe('ltr');
    });

    it('handles hyphenated locale variants', function (): void {
        expect($this->lingua->getDirection('ar-SA'))->toBe('rtl');
        expect($this->lingua->getDirection('en-US'))->toBe('ltr');
    });

    it('handles case variations', function (): void {
        expect($this->lingua->getDirection('AR'))->toBe('rtl');
        expect($this->lingua->getDirection('EN'))->toBe('ltr');
    });
});

describe('RTL detection with custom configuration', function (): void {
    it('respects custom RTL locales in isRtl check', function (): void {
        // Add a fictional RTL locale
        config(['lingua.rtl_locales' => ['ar', 'xx']]);

        expect($this->lingua->isRtl('xx'))->toBeTrue();
        expect($this->lingua->isRtl('he'))->toBeFalse(); // Removed from config
    });

    it('respects custom RTL locales in getDirection check', function (): void {
        config(['lingua.rtl_locales' => ['ar', 'yy']]);

        expect($this->lingua->getDirection('yy'))->toBe('rtl');
        expect($this->lingua->getDirection('he'))->toBe('ltr'); // Removed from config
    });

    it('works with locale variants when custom RTL locales configured', function (): void {
        config(['lingua.rtl_locales' => ['zz']]);

        expect($this->lingua->isRtl('zz'))->toBeTrue();
        expect($this->lingua->isRtl('zz_AA'))->toBeTrue();
        expect($this->lingua->isRtl('zz-BB'))->toBeTrue();
    });
});
