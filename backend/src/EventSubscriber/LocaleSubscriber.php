<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private const SUPPORTED_LOCALES = ['en', 'zh'];
    private const DEFAULT_LOCALE = 'en';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 100]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Check for explicit locale in query parameter or header
        $locale = $request->query->get('locale')
            ?? $request->headers->get('X-Locale')
            ?? $this->parseAcceptLanguage($request->headers->get('Accept-Language'));

        if ($locale && in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $request->setLocale($locale);
        } else {
            $request->setLocale(self::DEFAULT_LOCALE);
        }
    }

    private function parseAcceptLanguage(?string $acceptLanguage): ?string
    {
        if ($acceptLanguage === null || $acceptLanguage === '') {
            return null;
        }

        // Parse Accept-Language header
        // Format: en-US,en;q=0.9,zh-CN;q=0.8,zh;q=0.7
        $languages = [];
        $parts = explode(',', $acceptLanguage);

        foreach ($parts as $part) {
            $part = trim($part);
            $segments = explode(';', $part);
            $lang = trim($segments[0]);
            $quality = 1.0;

            if (isset($segments[1])) {
                $qPart = trim($segments[1]);
                if (str_starts_with($qPart, 'q=')) {
                    $quality = (float) substr($qPart, 2);
                }
            }

            // Extract primary language code (e.g., 'en' from 'en-US')
            $primaryLang = explode('-', $lang)[0];

            if (!isset($languages[$primaryLang]) || $languages[$primaryLang] < $quality) {
                $languages[$primaryLang] = $quality;
            }
        }

        // Sort by quality
        arsort($languages);

        // Return first supported locale
        foreach (array_keys($languages) as $lang) {
            if (in_array($lang, self::SUPPORTED_LOCALES, true)) {
                return $lang;
            }
        }

        return null;
    }
}
