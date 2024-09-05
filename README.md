# ai_filemetadata

Automatically generates FAL metadata (alternative texts, for the time being) for files by means of public LLMs. 

This extension helps you to **automate the process of generating such alternative texts** for images. It serves as a preparation for **EU Directive 2019/882** on the accessibility requirements for products and services (German: Barrierefreiheitsstärkungsgesetz (BFSG)).

The extension generates descriptive text for images, making them accessible to visually impaired individuals, 
e.g. when using screen reader software. It is not intended to create SEO-related texts.


https://github.com/user-attachments/assets/b0a632f1-0ca3-412b-9885-8dd4778308dd


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

## Support
Free Support is available via [Github Issue Tracker](https://github.com/marketing-factory/ai-filemetadata/issues)
For commercial support, please contact us at [info@marketing-factory.de](mailto:info@marketing-factory.de)
