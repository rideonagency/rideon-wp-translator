# RideOn WP Translator

> Automatic WordPress post translation powered by OpenAI — one click, three languages.

![Version](https://img.shields.io/badge/version-1.0.1-blue)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-21759B?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)
![OpenAI](https://img.shields.io/badge/powered%20by-OpenAI-412991?logo=openai&logoColor=white)
![License](https://img.shields.io/badge/license-GPL%20v2-green)

---

## Features

- **One-click translation** from the post editor metabox
- **In-place translation** — content is updated directly in the current post
- **3 languages supported**: Italian, English, Spanish
- **OpenAI models**: GPT-3.5 Turbo and GPT-4 Turbo
- **Preserves structure**: categories, tags, featured image, and author are kept
- **Optional debug logging** for API troubleshooting

## Requirements

- WordPress 5.0+
- PHP 7.4+
- A valid OpenAI API key with billing configured

## Installation

1. Upload the `rideon-wp-translator` folder to `/wp-content/plugins/`
2. Activate the plugin from the **Plugins** menu in WordPress
3. Go to **Settings → RideOn Translator** and enter your OpenAI API key

## Usage

Open any post in the editor, find the **RideOn Translator** metabox in the sidebar, select a target language, and click **Translate**.

## Documentation

See the [full documentation](docs/README.md) for configuration details, architecture overview, and developer notes.

## License

GPL v2 or later — [Ride On Agency](https://rideonagency.com)
