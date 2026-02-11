# RideOn WP Translator

WordPress plugin for automatic translation of posts from one language to another using OpenAI API.

## Description

RideOn WP Translator allows you to automatically translate WordPress posts from a source language to a target language using OpenAI's powerful language models. The plugin integrates seamlessly with WordPress admin interface and provides a simple, user-friendly way to translate content.

## Features

- **Automatic Translation**: Translate posts with a single click
- **OpenAI Integration**: Uses OpenAI GPT models for high-quality translations
- **Multiple Languages**: Support for Italian, English, Spanish, French, German, Portuguese, Russian, Chinese, Japanese, Korean, and Arabic
- **Draft Creation**: Translated posts are created as drafts for review before publishing
- **Translation Linking**: Original and translated posts are linked for easy management
- **Multisite Compatible**: Can be activated individually for each site in a multisite installation (not network-enabled)

## Installation

1. Upload the `rideon-wp-translator` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → RideOn Translator to configure your OpenAI API key
4. Enter your API key from https://platform.openai.com/api-keys
5. Select your preferred OpenAI model and default languages
6. Save settings

## Configuration

### OpenAI API Key

1. Create an account at https://platform.openai.com
2. Navigate to API Keys section
3. Create a new API key
4. Copy the key and paste it in the plugin settings
5. Configure billing limits as needed on OpenAI platform

### Settings

- **API Key**: Your OpenAI API key (required)
- **Model**: Choose between GPT-3.5 Turbo (faster, lower cost) or GPT-4 Turbo (higher quality)
- **Default Source Language**: Language of your original content
- **Default Target Language**: Language to translate to by default

## Usage

1. Edit any post in WordPress
2. Find the "RideOn Translator" metabox in the sidebar
3. Select the target language from the dropdown
4. Click "Translate" button
5. Wait for the translation to complete
6. The translated post will be created as a draft
7. Review and publish the translated post when ready

## Multisite Compatibility

This plugin is **not** network-enabled. It must be activated individually for each site in a multisite installation. Each site will have its own settings (API key, model, default languages).

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Valid OpenAI API key with billing configured
- Active internet connection for API calls

## Security

- API keys are encrypted before storage
- All user inputs are sanitized
- Nonce verification for AJAX requests
- Capability checks for user permissions
- All outputs are properly escaped

## Support

For issues, questions, or contributions, please visit the plugin repository on GitHub.

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Basic translation functionality
- OpenAI API integration
- Admin settings page
- Post translation metabox
