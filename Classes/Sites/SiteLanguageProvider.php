<?php

namespace Mfd\Ai\FileMetadata\Sites;

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

final readonly class SiteLanguageProvider
{
    public function __construct(private SiteFinder $siteFinder)
    {
    }

    public function getFalLanguages(): array
    {
        $languages = [];

        $sites = $this->siteFinder->getAllSites();
        $locale = null;

        if ($sites === []) {
            return [];
        }

        /** @var Site $site */
        foreach ($sites as $site) {
            $defaultLocale = $site->getDefaultLanguage()->getLocale()->posixFormatted();

            if (!isset($languages[0])) {
                $languages[0] = $defaultLocale;
            }

            foreach ($site->getAllLanguages() as $siteLanguage) {
                if (!isset($languages[$siteLanguage->getLanguageId()])) {
                    $languages[$siteLanguage->getLanguageId()] = $siteLanguage->getLocale()->posixFormatted();
                }
            }
        }

        return $languages;
    }
}
