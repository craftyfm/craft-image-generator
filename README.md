# Image Generator for Craft CMS


## Overview

The Image Generator plugin extends Craft CMS elements with the ability to generate custom images.  
It is useful for creating dynamic images (such as Open Graph images, social share previews, or banners) based on Craft element content.  

This plugin uses [Browsershot](https://github.com/spatie/browsershot) under the hood, which requires **Puppeteer** to be installed.  
Follow the Puppeteer installation guide here: [Puppeteer Installation](https://pptr.dev/guides/getting-started#installation).

---

## Installation

You can install the plugin in two ways:

### Option 1: Craft CMS Plugin Store  
Go to the **Plugin Store** in the Craft Control Panel, search for **Image Generator**, and click **Install**.

### Option 2: Composer  
Install via Composer:

```bash
composer require craftyfm/image-generator
````

Then enable it in the Craft Control Panel.

---

## Requirements

This plugin requires the following to work properly:

- **Node.js** (v22 or higher) – [Download here](https://nodejs.org/)
- **Puppeteer** – used via [Browsershot](https://github.com/spatie/browsershot) for image generation
- **Chromium** – Puppeteer requires a Chrome/Chromium installation

Make sure these dependencies are installed and accessible on your system before using the plugin.
---

## Plugin Settings

The plugin provides several configuration options in the Control Panel:

* **NPM Path** – Path to the `npm` binary.
* **Node Path** – Path to the `node` binary.
* **Chrome Path** *(optional)* – Path to the Chrome/Chromium binary.

If not set, Browsershot will use its default.


* **Asset Volume** – The asset volume where generated images will be stored.
* **Folder Path** – The folder inside the chosen asset volume where images will be saved. *(Required)*

---

## Usage

### 1. Define Image Types

Navigate to **Image Generator → Types** and create a new type.
Each type includes:

* **Name** – Human-friendly label.
* **Handle** – Unique identifier for referencing the type.
* **Width** – Image width in pixels.
* **Height** – Image height in pixels.
* **Format** – Image format (`jpg`, `png`, `webp`).
* **Quality** – Image quality (0–100).
* **Template** – Template used to render the image.

In the image template, you can use the `element` variable to access the content data of the source element.

---

### 2. Generate and Display Images

To output a generated image in your templates, use:

```twig
{{ craft.imageGenerator.getUrl('typeHandle', element) }}
```

* `typeHandle` – The handle of the image type you created.
* `element` – The element you want to generate an image for (e.g., Entry, Category, or any Craft element).

The image will be generated on-demand when the page is loaded, using the same mechanism as Craft’s built-in image transforms.

---

## Notes

* Puppeteer and its dependencies must be installed and accessible from the server.
* Generated images are stored in the configured Asset Volume.
* The system will automatically reuse previously generated images unless the source element has been updated.

---

## License

This plugin is released under the MIT License.
