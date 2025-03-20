<?php

declare(strict_types=1);

/**
 * @see https://github.com/eliashaeussler/typo3-warming/blob/main/Classes/Extension.php
 * Original Copyright (C) 2023 Elias Häußler <elias@haeussler.dev>
 */
/*
 * This file is part of the TYPO3 CMS extension "ai-filemetadata".
 *
 *
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Mfd\Ai\FileMetadata;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Extension
 *
 * @author Ingo Schmitt <ingo.schmitt@marketing-factory.de>
 * @license GPL-3.0-or-later

 */
final class Extension
{

    /**
     * Load additional libraries provided by PHAR file (only to be used in non-Composer-mode).
     *
     * FOR USE IN ext_localconf.php AND NON-COMPOSER-MODE ONLY.
     */
    public static function loadVendorLibraries(): void
    {
        // Vendor libraries are already available in Composer mode
        if (Environment::isComposerMode()) {
            return;
        }

        $vendorPharFile = GeneralUtility::getFileAbsFileName('EXT:ai_filemetadata/Resources/Private/Libs/vendors.phar');

        if (file_exists($vendorPharFile)) {
            require 'phar://' . $vendorPharFile . '/vendor/autoload.php';
        }
    }
}
