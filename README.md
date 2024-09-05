# ai_filemetadata

Automatically generates FAL metadata (Alt Text) for files by means of public LLMs. 

This extension helps you to **generate automatically alt-text** for images as preparation for **EU-Directive 2019/882** 
on the accessibility requirements for products and services (German: Barrierefreiheitsstärkungsgesetz (BFSG)).

This extension generates descriptive text for images, making them accessible to visually impaired individuals, 
such as by screen readers. It is not intended to create SEO-focused alt texts.

## important

**This extension is processing images from your TYPO3 installation via external AI services. You should check with
your privacy and data protection policies if the usage of the services for the image data is allowed!**

## prequisites

* TYPO3 >= v12
* php >= 8.2
* API Key for chatgpt

## installation

`composer require mfd/ai-filemetadata`

## configuration

Acquire the API Key from Open-AI and configure the key in extension configuration. 

## Support
Free Support is available via [Github Issue Tracker](https://github.com/marketing-factory/ai-filemetadata/issues)
For commercial support, please contact us at [info@marketing-factory.de](mailto:info@marketing-factory.de)