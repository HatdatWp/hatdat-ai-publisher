=== Hatdat AI Publisher ===
Contributors: peterliebetrau
Tags: ai, openai, seo, content, publishing
Requires at least: 6.8
Requires PHP: 8.1
Tested up to: 7.0
Stable tag: 1.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hatdat AI Publisher helps administrators create WordPress posts, featured images and SEO metadata using the OpenAI API.

== Description ==

Hatdat AI Publisher is an editorial assistant for WordPress administrators. It can generate draft posts, featured image prompts, featured images and SEO metadata for supported SEO plugins.

The plugin is designed for review-based publishing workflows. Generated content should be reviewed by a human editor before publication, especially for legal, medical, financial or other sensitive topics.

== OpenAI API account and costs ==

Hatdat AI Publisher requires a separate OpenAI API account and an OpenAI API key. A ChatGPT subscription does not automatically include OpenAI API credits.

Using the OpenAI API may generate additional costs billed by OpenAI. Text generation and image generation are billed separately by OpenAI. Image generation can be a significant part of the total cost.

Hatdat AI Publisher includes local cost estimates based on the rates configured in the plugin settings. These estimates are approximate and may differ from the final OpenAI invoice.

== Privacy ==

Hatdat AI Publisher can transmit prompts, article content and image generation requests entered by administrators to the OpenAI API for processing.

Hatdat AI Publisher does not automatically transmit WordPress user accounts, passwords, email addresses, profile data or other WordPress user records to OpenAI.

Only content intentionally submitted through the Hatdat AI Publisher administration interface is transmitted to OpenAI.

Site administrators are responsible for ensuring that content submitted to OpenAI through Hatdat AI Publisher complies with applicable privacy, copyright and data protection laws.

== Consent before use ==

After activation, Hatdat AI Publisher displays a notice in the WordPress admin area. AI generation features remain disabled until an administrator confirms the OpenAI data processing and cost notices in the plugin settings.

The required confirmations cover:

* prompts, article content and image generation requests are transmitted to OpenAI for processing;
* using the OpenAI API requires a separate OpenAI API account and may generate additional costs;
* the site administrator remains responsible for compliance with applicable privacy, copyright and data protection regulations.

== External services ==

Hatdat AI Publisher communicates with the OpenAI API only when an administrator intentionally starts an API-related action, such as generating an article, generating an image, or checking the API connection.

OpenAI privacy information: https://openai.com/policies/privacy-policy/
OpenAI API keys: https://platform.openai.com/api-keys
OpenAI billing: https://platform.openai.com/settings/organization/billing/overview
OpenAI usage limits: https://platform.openai.com/settings/organization/limits

== SEO plugins ==

Hatdat AI Publisher can write SEO metadata for Rank Math and Yoast SEO. SEO integration can also be disabled.

== Screenshots ==

1. Hatdat AI Publisher settings page
2. Prompt editor for article and image generation
3. AI-powered content generation form

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/hatdat-ai-publisher/`.
2. Activate Hatdat AI Publisher in the WordPress plugin admin screen.
3. Open Hatdat AI Publisher settings.
4. Enter your OpenAI API key.
5. Review and accept the OpenAI data processing and cost confirmations.
6. Configure the default models, image size and SEO provider.

== Changelog ==

= 1.1.5 =
* Renamed the plugin text domain to match the WordPress.org slug `hatdat-ai-publisher`.
* Updated translation file names after the plugin rename.

= 1.1.4 =
* Reduced remaining Plugin Check warnings for WordPress.org review preparation.
* Added explicit nonce-verification ignores for POST values handled after a central nonce check.
* Clarified custom prompt-table database access for static analysis.

= 1.1.3 =
* Fixed Plugin Check findings for WordPress.org review preparation.
* Added missing translator comments for placeholder-based translation strings.
* Hardened admin request handling with explicit nonce-checked input handling and sanitized notice values.
* Documented safe custom table database access for Plugin Check/WPCS.

= 1.1.2 =
* Fixed a potential fatal error in admin menu highlighting by making the WordPress menu filters compatible with non-string values.
* Kept the Hatdat AI Publisher top-level menu highlight behavior without strict type assumptions.

= 1.1.1 =
* Fixed admin menu highlighting so Hatdat AI Publisher pages keep the Hatdat AI Publisher top-level menu active instead of the WordPress Settings menu.
* Removed the duplicate Settings fallback menu entry to avoid incorrect parent menu selection.

= 1.1.0 =
* Improved prompt administration translations.
* Added an explicit action to create a new custom prompt without copying the protected standard prompt.

= 1.0.8 =
* Removed the legacy prompt custom post type code completely.
* Prompt management now uses only the dedicated `ai_publisher_prompts` database table.
* Added cleanup for old `ai_pub_prompt` posts created by previous development versions.

= 1.0.7 =
* Fix: Removed legacy prompt custom post type registration from the prompt migration to avoid rewrite-related activation/runtime fatal errors.
* Fix: Legacy prompt migration now reads existing prompt records directly from the posts table without registering rewrite tags.

= 1.0.6 =
* Moved prompt storage from a hidden custom post type to a dedicated database table.
* Added protected standard news article prompts in German, English, French and Spanish.
* The standard prompt is shown in the admin language when supported, with English as fallback.
* Standard prompts can no longer be edited or deleted directly; administrators can create editable copies instead.

= 1.0.5 =
* Strengthened the output-language instruction for generated content.
* The selected output language is now explicitly enforced for all user-facing JSON fields, including title, content, SEO metadata and image prompt.
* Added stricter instructions to prevent mixed-language introductions or meta commentary.

= 1.0.4 =
* Added an output language selector to the content generation screen.
* Default output language now follows the site language, independently from the admin/user language and prompt language.
* Added an explicit OpenAI instruction so generated titles, slugs, content, SEO metadata and image prompts use the selected output language.

= 1.0.3 =
* Fix: Register Hatdat AI Publisher admin pages more defensively and add a fallback settings page under Settings > Hatdat AI Publisher.
* Fix: Use one central admin capability for menu pages and form actions.


= 1.0.2 =
* Fixed activation robustness for the translated default news article prompt.
* Corrected the stable tag after the 1.0.1 packaging issue.

= 1.0.1 =
* Added translation support for the default news article prompt using WordPress admin/user language.

= 1.0.0 =
* Added OpenAI consent flow before using generation features.
* Added privacy policy integration for WordPress.
* Added extended privacy and external service documentation.
* Added multilingual documentation in English, German, French and Spanish.
* Updated version and stable tag to 1.0.0.
