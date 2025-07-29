# ai_filemetadata

Automatically generates FAL metadata (alternative texts, for the time being) for files by means of public LLMs.

This extension helps you to **automate the process of generating such alternative texts** for images. It serves as a preparation for **EU Directive 2019/882** on the accessibility requirements for products and services (German: Barrierefreiheitsstärkungsgesetz (BFSG)).

The extension generates descriptive text for images, making them accessible to visually impaired individuals,
e.g. when using screen reader software. It is not intended to create SEO-related texts.


https://github.com/user-attachments/assets/b0a632f1-0ca3-412b-9885-8dd4778308dd

Video about this extension (in German) is available on Youtube: https://www.youtube.com/watch?v=3nMkxx2E4CE



[Read more](https://www.marketing-factory.com/services/programming-and-development/custom-development/ai-filemetdadata/)


## Important

**This extension is processing images from your TYPO3 installation via external AI services. You should check
your privacy and data protection policies if the usage of the services for the image data is allowed! Please keep an eye on the license terms of your images, too, as some of them might be restricted from certain applications such as the use of LLM or AI technology.**

## Prerequisites

* TYPO3 >= v12
* PHP >= 8.2
* OpenAI API key (_not to be confused with "ChatGPT Teams" or "ChatGPT Enterprise"_)

## Installation

`composer require mfd/ai-filemetadata`

## Configuration

Acquire the [OpenAI API key from Open-AI](https://platform.openai.com/docs/quickstart) and place the key in extension configuration.

### Language Mapping

Since folder do not have a relation to any of the sites default languages you might want to define a mapping for certain folders of your storages. You can do so by add this to your `system/settings.php`:

```php
    'EXTCONF' => [
        'ai_filemetadata' => [
            'falLanguageMappings' => [
                '1:/site-a/' => [0 => 'en_EN.utf-8', 1 => 'de_DE.utf-8'],
                '1:/site-b/' => [0 => 'fr_FR.utf-8', 2 => 'en_EN.utf-8'],
                '1:/site-c/' => [0 => 'de_CH.utf-8', 3 => 'it_CH.utf-8', 4 => 'fr_CH.utf-8'],
            ],
        ],
    ],
```

This defines the locales being used for each `sys_language_uid` per folder.

### Exclude folders

To exclude certain folders you can use this in your `system/settings.php`:

```php
   'EXTCONF' => [
        'ai_filemetadata' => [
            'falExcludedPrefixes' => [
                '1:/site-a/nudes/',
            ],
        ],
    ],
```

### Resize images for LLM processing

Sending large images to OpenAI language models can consume an extremely large number of tokens leading to higher costs, see https://platform.openai.com/docs/guides/images-vision?api-mode=chat#calculating-costs.
In most cases, an image size of no more than 512x512px is perfectly adequate for image analysis.

```php
   'EXTENSIONS' => [
        'ai_filemetadata' => [
            'imageResizing' => '512',
        ],
    ],
```

## CLI command

```bash
bin/typo3 ai:generate-alt-texts --path="1:site-a/my-subfolder/" [--overwrite] [--limit=1]
```

This generates alt texts for all files within the given `--path` for all available languages. To avoid loading unnecessary translations you might want set a language mapping for certain folders. See chapter above.

## Support
Free Support is available via [Github Issue Tracker](https://github.com/marketing-factory/ai-filemetadata/issues)
For commercial support, please contact us at [info@marketing-factory.de](mailto:info@marketing-factory.de)
